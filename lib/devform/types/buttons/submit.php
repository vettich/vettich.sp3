<?php
namespace vettich\sp3\devform\types\buttons;

/**
*
*/
class submit extends simple
{
	public $template = '<input {params} name="{name}" value="{title}">';
	public $params = ['type' => 'submit'];
}
