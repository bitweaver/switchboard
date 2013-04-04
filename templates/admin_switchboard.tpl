{strip}
{form}
		<input type="hidden" name="page" value="{$page}" />
{jstabs}
	{jstab title="Global Settings"}
	{legend legend="Global Settings"}
		<div class="control-group">
			{formlabel label="Default Transport"}
			{forminput}	
				<select name="switchboard_default_transport">
					<option value=""></option>
					{foreach from=$gSwitchboardSystem->mTransports key=style item=options}
					<option value="{$style}" {if $gSwitchboardSystem->getDefaultTransport() == $style}selected="selected"{/if}/>{$style|capitalize:true}</option>
					{/foreach}
				</select>
			{/forminput}
		</div>
	{/legend}
	{/jstab}
{foreach from=$transportConfigs key=transport item=transportConfigTpl}
	{jstab title=$transport|ucwords}
		{include file=$transportConfigTpl}
	{/jstab}
{/foreach}
{/jstabs}
		<div class="control-group submit">
			<input type="submit" class="btn" name="apply" value="{tr}Change preferences{/tr}" />
		</div>
{/form}
{/strip}
