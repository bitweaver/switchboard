{strip}
	<table class="switchboard">
		<tr>
			<th>{tr}Package{/tr}</th>
			<th>{tr}Event{/tr}</th>
			<th>{tr}Send Using{/tr}</th>
		</tr>
		{foreach from=$gSwitchboardSystem->mSenders key=package item=types}
			<tr>	
				<td rowspan="{$types.types|@count}"><h2>{$package|capitalize:true}</h2></td>
				{foreach from=$types.types item=type name=type}
					{if !$smarty.foreach.type.first}
						<tr>
					{/if}
					<td><h3>{$type|capitalize:true}</h3></td>
					<td>
						<ul>
							{if $includeDefaultSend}
								<li>{tr}Default{/tr}: <input type="radio" name="{$prefs_table_value_prefix}[{$package}][{$type}]" value="default" {if $prefs_data.$package.$type.delivery_style == 'default'}checked{/if} /></li>
							{/if}

							<li>{tr}Don't Send{/tr}: <input type="radio" name="{$prefs_table_value_prefix}[{$package}][{$type}]" value="none" {if empty($prefs_data.$package.$type) || $prefs_data.$package.$type.delivery_style == 'none'}checked{/if} /></li>

							{foreach from=$gSwitchboardSystem->mListeners key=style item=options}
								<li>{$style|capitalize:true} <input type="radio" name="{$prefs_table_value_prefix}[{$package}][{$type}]" value="{$style}" {if $prefs_data.$package.$type.delivery_style == $style}checked{/if}/></li>
							{/foreach}
						</ul>
					</td>
					{if !$smarty.foreach.type.first}
						</tr>
					{/if}			
				{/foreach}
			</tr>
		{/foreach}
	</table>
{/strip}
