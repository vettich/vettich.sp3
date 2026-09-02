#!/usr/bin/env php
<?php

/**
 * Smoke tests for DomainSelector / DomainCache / Api circuit breaker
 * (fail_streak, last_ok race, sort penalty, markDomainSuccess, pp_down_until).
 *
 * No live Bitrix, no HTTP: cache is seeded; probes are not invoked
 * (last_check_domains is kept fresh so MIN_PROBE_INTERVAL / cold-start curl never run).
 *
 * Usage: php bin/test-bx-domains.php
 */

require_once __DIR__.'/test-bootstrap.php';
require_once dirname(__DIR__).'/lib/module.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/domaincache.php';
require_once dirname(__DIR__).'/lib/domainselector.php';
require_once dirname(__DIR__).'/lib/log.php';
require_once dirname(__DIR__).'/lib/api.php';
require_once dirname(__DIR__).'/install/index.php';

use vettich\sp3\Api;
use vettich\sp3\DomainCache;
use vettich\sp3\DomainSelector;

const TEST_A = 'https://a.example.test';
const TEST_B = 'https://b.example.test';

/**
 * @param array<int, array<string, mixed>> $domains
 * @param array<string, mixed>             $extra
 */
function seed(array $domains, array $extra = [])
{
	DomainCache::save(array_merge([
		'available_domains'  => $domains,
		'last_check_domains' => time(),
		'errors'             => [],
		'pp_down_until'      => 0,
	], $extra));
}

/**
 * @param array<string, mixed> $over
 *
 * @return array<string, mixed>
 */
function entry($domain, $ping, $failStreak = 0, array $over = [])
{
	$available = $failStreak < 2;

	return array_merge([
		'domain'          => $domain,
		'ping'            => $ping,
		'available'       => $available,
		'fail_streak'     => $failStreak,
		'last_ok'         => time(),
		'last_error_kind' => null,
	], $over);
}

/**
 * @return array<string, mixed>
 */
function domain_entry($domain)
{
	foreach (DomainCache::load()['available_domains'] as $raw) {
		$e = pp_test_call_private(DomainSelector::class, 'normalizeDomainEntry', is_array($raw) ? $raw : []);
		if ($e['domain'] === $domain) {
			return $e;
		}
	}
	fwrite(STDERR, "FAIL: no cache entry for {$domain}\n");
	exit(1);
}

function apply_probe(array $prev, array $probe, $probeStartedAt)
{
	$probe['domain'] = $probe['domain'] ?? ($prev['domain'] ?? '');

	return pp_test_call_private(
		DomainSelector::class,
		'applyProbeToEntry',
		$prev,
		$probe,
		$probeStartedAt
	);
}

// --- DomainCache: pp_down_until survives normalize --------------------------------

$empty = DomainCache::emptyState();
assert_eq('emptyState has pp_down_until', 0, $empty['pp_down_until']);

$norm = DomainCache::normalizeState(['pp_down_until' => '90', 'available_domains' => []]);
assert_eq('normalizeState casts pp_down_until', 90, $norm['pp_down_until']);

seed([entry(TEST_A, 10)]);
assert_true('cache file is under isolated DOCUMENT_ROOT', strpos(DomainCache::filePath(), $_SERVER['DOCUMENT_ROOT']) === 0);

// --- normalizeDomainEntry: legacy cache without fail_streak -----------------------

$legacyOk = pp_test_call_private(DomainSelector::class, 'normalizeDomainEntry', [
	'domain'    => TEST_A,
	'ping'      => 40,
	'available' => true,
]);
assert_eq('legacy healthy -> fail_streak 0', 0, $legacyOk['fail_streak']);
assert_eq('legacy healthy -> available', true, $legacyOk['available']);

$legacyDead = pp_test_call_private(DomainSelector::class, 'normalizeDomainEntry', [
	'domain'    => TEST_A,
	'ping'      => -1,
	'available' => false,
]);
assert_eq('legacy dead -> fail_streak at threshold', 2, $legacyDead['fail_streak']);
assert_eq('legacy dead -> unavailable', false, $legacyDead['available']);

$legacyPingOnly = pp_test_call_private(DomainSelector::class, 'normalizeDomainEntry', [
	'domain'    => TEST_A,
	'ping'      => 0,
	'available' => true,
]);
assert_eq('legacy available but ping<=0 -> treated dead', 2, $legacyPingOnly['fail_streak']);

$withStreak = pp_test_call_private(DomainSelector::class, 'normalizeDomainEntry', [
	'domain'      => TEST_A,
	'ping'        => 15,
	'fail_streak' => 1,
	'available'   => false, // ignored when fail_streak is present
]);
assert_eq('fail_streak present -> available derived from threshold', true, $withStreak['available']);
assert_eq('unknown last_error_kind dropped', null, pp_test_call_private(
	DomainSelector::class,
	'normalizeDomainEntry',
	['domain' => TEST_A, 'fail_streak' => 0, 'last_error_kind' => 'other']
)['last_error_kind']);

// --- applyProbeToEntry ------------------------------------------------------------

$fresh = pp_test_call_private(DomainSelector::class, 'emptyDomainEntry', TEST_A);
$ok = apply_probe($fresh, ['ok' => true, 'ping' => 42, 'kind' => null], time());
assert_eq('probe ok -> ping', 42, $ok['ping']);
assert_eq('probe ok -> streak 0', 0, $ok['fail_streak']);
assert_eq('probe ok -> available', true, $ok['available']);
assert_eq('probe ok -> last_error_kind null', null, $ok['last_error_kind']);
assert_true('probe ok -> last_ok set', $ok['last_ok'] >= time() - 1);

$prevPing = apply_probe(
	array_merge($fresh, ['ping' => 99, 'fail_streak' => 0]),
	['ok' => true, 'ping' => 0, 'kind' => null],
	time()
);
assert_eq('probe ok with ping 0 keeps previous ping', 99, $prevPing['ping']);

$t1 = apply_probe($fresh, ['ok' => false, 'ping' => -1, 'kind' => 'timeout'], time() - 5);
assert_eq('timeout +1 streak', 1, $t1['fail_streak']);
assert_eq('timeout still available under threshold', true, $t1['available']);
assert_eq('timeout kind stored', 'timeout', $t1['last_error_kind']);

$t2 = apply_probe($t1, ['ok' => false, 'ping' => -1, 'kind' => 'timeout'], time() - 5);
assert_eq('second timeout reaches threshold', 2, $t2['fail_streak']);
assert_eq('second timeout -> unavailable', false, $t2['available']);

$hard = apply_probe($fresh, ['ok' => false, 'ping' => -1, 'kind' => 'hard'], time() - 5);
assert_eq('hard fail jumps to threshold', 2, $hard['fail_streak']);
assert_eq('hard fail -> unavailable', false, $hard['available']);
assert_eq('hard kind stored', 'hard', $hard['last_error_kind']);

$recovered = apply_probe($hard, ['ok' => true, 'ping' => 12, 'kind' => null], time());
assert_eq('success after hard fail resets streak', 0, $recovered['fail_streak']);
assert_eq('success after hard fail -> available', true, $recovered['available']);

$racePrev = array_merge($fresh, [
	'fail_streak' => 0,
	'available'   => true,
	'last_ok'     => time(),
	'ping'        => 30,
]);
$raced = apply_probe($racePrev, ['ok' => false, 'ping' => -1, 'kind' => 'hard'], time() - 2);
assert_eq('last_ok during probe ignores fail', 0, $raced['fail_streak']);
assert_eq('last_ok during probe keeps ping', 30, $raced['ping']);
assert_eq('last_ok during probe stays available', true, $raced['available']);

// --- classifyProbeFailure ---------------------------------------------------------

assert_eq(
	'timeout errno is timeout',
	'timeout',
	pp_test_call_private(DomainSelector::class, 'classifyProbeFailure', CURLE_OPERATION_TIMEDOUT)
);
assert_eq(
	'resolve errno is hard',
	'hard',
	pp_test_call_private(DomainSelector::class, 'classifyProbeFailure', CURLE_COULDNT_RESOLVE_HOST)
);
assert_eq(
	'http non-200 (errno 0) is timeout',
	'timeout',
	pp_test_call_private(DomainSelector::class, 'classifyProbeFailure', 0)
);

// --- hasDegradedDomain / sortKey --------------------------------------------------

assert_eq(
	'empty list is not degraded',
	false,
	pp_test_call_private(DomainSelector::class, 'hasDegradedDomain', ['available_domains' => []])
);
assert_eq(
	'all healthy is not degraded',
	false,
	pp_test_call_private(DomainSelector::class, 'hasDegradedDomain', [
		'available_domains' => [entry(TEST_A, 10), entry(TEST_B, 20)],
	])
);
assert_eq(
	'fail_streak>0 is degraded even if still available',
	true,
	pp_test_call_private(DomainSelector::class, 'hasDegradedDomain', [
		'available_domains' => [entry(TEST_A, 10, 1)],
	])
);
assert_eq(
	'unavailable domain is degraded',
	true,
	pp_test_call_private(DomainSelector::class, 'hasDegradedDomain', [
		'available_domains' => [entry(TEST_A, -1, 2)],
	])
);

assert_eq(
	'sortKey healthy ping',
	40,
	pp_test_call_private(DomainSelector::class, 'sortKey', ['ping' => 40, 'fail_streak' => 0])
);
assert_eq(
	'sortKey adds 5000 penalty when streak>0',
	5020,
	pp_test_call_private(DomainSelector::class, 'sortKey', ['ping' => 20, 'fail_streak' => 1])
);

// --- getBestDomain / getPriorityDomains (seeded, no curl) -------------------------

seed([
	entry(TEST_A, 80, 0),
	entry(TEST_B, 20, 1),
]);
assert_eq(
	'getBestDomain prefers healthy over faster degraded',
	TEST_A,
	DomainSelector::getBestDomain()
);
assert_eq(
	'getPriorityDomains same order',
	[TEST_A, TEST_B],
	DomainSelector::getPriorityDomains()
);

seed([
	entry(TEST_A, 80, 0),
	entry(TEST_B, 20, 0),
]);
assert_eq('getBestDomain fastest when both healthy', TEST_B, DomainSelector::getBestDomain());

seed([
	entry(TEST_A, 10, 2),
	entry(TEST_B, 10, 2),
]);
assert_eq('getBestDomain false when none available', false, DomainSelector::getBestDomain());

$hashA = md5(TEST_A);
seed(
	[entry(TEST_A, 5, 0), entry(TEST_B, 50, 0)],
	['errors' => [$hashA => [time(), time()]]]
);
assert_eq(
	'getPriorityDomains drops domain with recent errors',
	[TEST_B],
	DomainSelector::getPriorityDomains()
);
assert_eq(
	'getBestDomain still returns error-tagged domain (errors only filter priority list)',
	TEST_A,
	DomainSelector::getBestDomain()
);

// --- markDomainSuccess ------------------------------------------------------------

seed([entry(TEST_A, 10, 2, ['last_error_kind' => 'hard'])], [
	'errors' => [$hashA => [time()]],
]);
DomainSelector::markDomainSuccess(TEST_A);
$afterOk = domain_entry(TEST_A);
assert_eq('markDomainSuccess clears streak', 0, $afterOk['fail_streak']);
assert_eq('markDomainSuccess sets available', true, $afterOk['available']);
assert_eq('markDomainSuccess clears last_error_kind', null, $afterOk['last_error_kind']);
assert_true('markDomainSuccess drops errors[]', empty(DomainCache::load()['errors'][$hashA]));

$staleOk = time() - 5;
seed([entry(TEST_A, 10, 0, ['last_ok' => $staleOk])]);
DomainSelector::markDomainSuccess(TEST_A);
assert_eq(
	'markDomainSuccess no-op when healthy and last_ok fresh',
	$staleOk,
	domain_entry(TEST_A)['last_ok']
);

seed([]);
DomainSelector::markDomainSuccess(TEST_A);
assert_eq('markDomainSuccess inserts configured domain', TEST_A, domain_entry(TEST_A)['domain']);
assert_eq('inserted domain is available', true, domain_entry(TEST_A)['available']);

seed([entry(TEST_A, 10, 0)]);
DomainSelector::markDomainSuccess('https://other.example.test');
assert_eq('unconfigured domain is not inserted', 1, count(DomainCache::load()['available_domains']));
assert_eq('configured domain kept', TEST_A, domain_entry(TEST_A)['domain']);

DomainSelector::markDomainSuccess('');
assert_eq('empty domain is ignored', 1, count(DomainCache::load()['available_domains']));

// --- markDomainError (forceRefresh no-ops: last_check is fresh) -------------------

seed([entry(TEST_A, 10, 0)]);
DomainSelector::markDomainError(TEST_A);
$err1 = DomainCache::load()['errors'][$hashA] ?? [];
assert_eq('markDomainError records one timestamp', 1, count($err1));
DomainSelector::markDomainError(TEST_A);
$err2 = DomainCache::load()['errors'][$hashA] ?? [];
assert_eq('second markDomainError reaches threshold', 2, count($err2));
assert_eq(
	'fresh last_check prevents probe after error threshold',
	TEST_A,
	domain_entry(TEST_A)['domain']
);

// --- agentRefreshDomains / ensureAgent (no CAgent, no curl) -----------------------

seed([entry(TEST_A, 10, 0)]);
$last = DomainCache::load()['last_check_domains'];
assert_eq(
	'agentRefreshDomains returns AGENT_NAME',
	DomainSelector::AGENT_NAME,
	DomainSelector::agentRefreshDomains()
);
assert_eq('healthy TTL does not probe when last_check is fresh', $last, DomainCache::load()['last_check_domains']);

$install = new ReflectionClass('vettich_sp3');
assert_eq(
	'install AGENT_DOMAINS matches DomainSelector::AGENT_NAME',
	DomainSelector::AGENT_NAME,
	$install->getConstant('AGENT_DOMAINS')
);

// --- Api circuit breaker (no doRequest: that would curl) --------------------------

seed([entry(TEST_A, 10, 0)], ['pp_down_until' => time() + 60]);
assert_eq(
	'circuit open when pp_down_until in the future',
	true,
	pp_test_call_private(Api::class, 'isPpDownCircuitOpen')
);

seed([entry(TEST_A, 10, 0)], ['pp_down_until' => time() - 1]);
assert_eq(
	'circuit closed when pp_down_until in the past',
	false,
	pp_test_call_private(Api::class, 'isPpDownCircuitOpen')
);

seed([]);
pp_test_call_private(Api::class, 'markPpUnavailableForCircuit');
$until = (int)DomainCache::load()['pp_down_until'];
assert_true('markPpUnavailableForCircuit sets window ~30s', $until >= time() + 25 && $until <= time() + 35);
assert_eq(
	'circuit open after markPpUnavailableForCircuit',
	true,
	pp_test_call_private(Api::class, 'isPpDownCircuitOpen')
);

pp_test_call_private(Api::class, 'clearPpDownCircuit');
assert_eq('clearPpDownCircuit zeroes pp_down_until', 0, (int)DomainCache::load()['pp_down_until']);
assert_eq(
	'circuit closed after clearPpDownCircuit',
	false,
	pp_test_call_private(Api::class, 'isPpDownCircuitOpen')
);

echo "\nAll tests passed.\n";
