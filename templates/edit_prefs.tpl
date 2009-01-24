{strip}
{if empty($gSwitchboardSystem->mListeners) }
	<div class="warning">{tr}No packages registered as listeners.{/tr}</div>
{else}
	{if empty($gSwitchboardSystem->mSenders)}
		<div class="warning">{tr}No packages registered as senders.{/tr}</div>
	{else}
		{form}
			{if !empty($switchboardContentId)}<input type="hidden" name="content_id" value="{$switchboardContentId}" />{/if}
			{include file="bitpackage:switchboard/prefs_table.tpl" prefs_table_value_prefix="SBDefault" prefs_data=$switchboardPrefs}

			{if !empty($switchboardContentPrefs)}
				<h2>{tr}Specific Preferences{/tr}</h2>	
				{jstabs}
					{foreach from=$switchboardContentPrefs key=contentId item=contentPrefs}
						{jstab title=$switchboardContentTitles.$contentId|escape:html}
							<h2>{$switchboardContentTitles.$contentId|escape:html}</h2>
							{capture assign=prefs_table_value_prefix}SBContent[{$contentId}]{/capture}
							{include file="bitpackage:switchboard/prefs_table.tpl" prefs_data=$contentPrefs includeDefaultSend=true}
						{/jstab}
					{/foreach}
				{/jstabs}
			{/if}

			<div class="row submit">
				<input type="submit" name="saveSwitchboardPrefs" value="Save Preferences" />
			</div>
		{/form}
	{/if}
{/if}
{/strip}
