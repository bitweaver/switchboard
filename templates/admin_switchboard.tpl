{strip}
{form}
		<input type="hidden" name="page" value="{$page}" />
	{legend legend="Global Settings"}
		<div class="row">
			{formlabel label="Default Notification"}
			{forminput}	
				<select name="switchboard_default_notification">

				<option value=""></option>
				{foreach from=$gSwitchboardSystem->mListeners key=style item=options}
					<option value="{$style}" {if $gBitSystem->getConfig('switchboard_default_notification') == $style}selected="selected"{/if}/>{$style|capitalize:true}</input>
				{/foreach}
				</select>
			{/forminput}
		</div>
	{/legend}
	{legend legend="Switchboard Mail Server Settings"}
		{foreach from=$formSwitchboardFeatures key=item item=output}
			<div class="row">
				{formlabel label=`$output.label` for=$item}
				{forminput}
					<input type="text" name="{$item|escape}" value="{$gBitSystem->getConfig($item,$output.default)|escape}"/>
					{formhelp note=`$output.note` page=`$output.page`}
				{/forminput}
			</div>
		{/foreach}
	{/legend}

		<div class="row submit">
			<input type="submit" name="apply" value="{tr}Change preferences{/tr}" />
		</div>
{/form}
{/strip}
