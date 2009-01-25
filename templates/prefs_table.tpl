{strip}
	<div class="switchboard">
		{foreach from=$gSwitchboardSystem->mSenders key=package item=types}
			{legend legend=$package}
			{foreach from=$types.types item=type name=type}
			<div class="row">
				{formlabel label=$type|capitalize:true}
				{forminput}	
					<select name="{$prefs_table_value_prefix}[{$package}][{$type}]">
					{foreach from=$gSwitchboardSystem->mTransports key=style item=options}
						<option value="{$style}" {if (empty($prefs_data.$package.$type.delivery_style) && $gSwitchboardSystem->getDefaultTransport() == $style) || $prefs_data.$package.$type.delivery_style == $style}selected="selected"{/if}/>{$style|capitalize:true}</input>
					{/foreach}
					</select>
				{/forminput}
			</div>
			{/foreach}
			{/legend}
		{/foreach}
	</div>
{/strip}
