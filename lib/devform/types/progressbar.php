<?php
namespace vettich\sp3\devform\types;

/**
* @author Oleg Lenshin (Vettich)
*/
class progressbar extends _type
{
	public $content = '<div class="instal-progress-bar-outer" style="width: 34vw;" id="{id}">
		<div class="instal-progress-bar-alignment">
			<div class="instal-progress-bar-inner" id="{id}-indicator" style="width: {default_value}%;">
				<div class="instal-progress-bar-inner-text" style="width: 34vw;" id="{id}-percent">{default_value}%</div>
			</div>
			<span id="{id}-percent2">{default_value}%</span>
		</div>
	</div>';
}
