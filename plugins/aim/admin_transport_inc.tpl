	{legend legend="Aim Transport Settings"}
		{foreach from=$formTransportAim key=item item=output}
		<div class="row">
			{formlabel label=`$output.label` for=$item}
			{forminput}
				<input type="text" name="{$item|escape}" value="{$gBitSystem->getConfig($item,$output.default)|escape}"/>
				{formhelp note=`$output.note` page=`$output.page`}
			{/forminput}
		</div>
		{/foreach}
	{/legend}
