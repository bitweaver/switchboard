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

		{foreach from=$formSwitchboardChecks key=item item=output}
			<div class="row">
				{formlabel label=`$output.label` for=$item}
				{forminput}
					{html_checkboxes name="$item" values="y" checked=$gBitSystem->getConfig($item) labels=false id=$item}
					{formhelp note=`$output.note` page=`$output.page`}
				{/forminput}
			</div>
		{/foreach}
	{/legend}
