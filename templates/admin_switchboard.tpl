{strip}
{form}
	{legend legend="Switchboard Mail Server Settings"}
		<input type="hidden" name="page" value="{$page}" />
		{foreach from=$formSwitchboardFeatures key=item item=output}
			<div class="row">
				{formlabel label=`$output.label` for=$item}
				{forminput}
					<input type="text" name="{$item|escape}" value="{$gBitSystem->getConfig($item,$output.default)|escape}"/>
					{formhelp note=`$output.note` page=`$output.page`}
				{/forminput}
			</div>
		{/foreach}

		<div class="row submit">
			<input type="submit" name="apply" value="{tr}Change preferences{/tr}" />
		</div>
	{/legend}
{/form}
{/strip}
