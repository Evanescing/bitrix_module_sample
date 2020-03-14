<?
/**
 * @var string $REQUEST_METHOD
 * @var null|string $Update
 * @var null|string $Apply
 * @var null|string $RestoreDefaults
 * @var string $mid
 *
 * @global $APPLICATION
 * @global $USER
 * @const LANGUAGE_ID
 */
$mid = "eva.administration";
$POST_RIGHT = $APPLICATION->GetGroupRight($mid);

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Eva\Administration\Tables,
	Eva\Administration\Scripts;

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/options.php');
Loc::loadMessages(__FILE__);

Loader::includeModule($mid);
Loader::includeModule("sale");

$arTabs = array(
    array(
        'DIV' => 'eva_settings',
        'TAB' => Loc::getMessage('EVA_SETTINGS'),
        'TITLE' => Loc::getMessage('EVA_SETTINGS'),
        'ICON' => ''
    ),
    array(
        'DIV' => 'group_rights',
        'TAB' => Loc::getMessage('EVA_GROUP_RIGHTS'),
        'TITLE' => Loc::getMessage('EVA_GROUP_RIGHTS'),
        'ICON' => ''
    )
);

$sPage = $APPLICATION->GetCurPage() . '?mid=' . urlencode($mid) . '&amp;lang=' . urlencode(LANGUAGE_ID);

if ($REQUEST_METHOD == 'POST' && check_bitrix_sessid() && $POST_RIGHT=="W" && (isset($Update) || isset($Apply) || isset($RestoreDefaults))) {
    if (isset($RestoreDefaults)) {
        Option::delete($mid);
    } elseif (!empty($options)) {
        foreach ($options as $sOptionCode => $sValue) {
            Option::set($mid, $sOptionCode, $sValue);
        }
		
		ob_start();
		require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");
		ob_end_clean();

        LocalRedirect($sPage);
    }
}

$obTabControl = new CAdminTabControl('tabControl', $arTabs);
$obTabControl->Begin();

//Получаем плат. системы
$db_ptype = CSalePaySystem::GetList($arOrder = Array("SORT"=>"ASC", "ID"=>"ASC"), Array("LID"=>SITE_ID, "ACTIVE"=>"Y"));//PSA_NAME
$bFirst = True;
////////////////////////////////////////////////////////////////////////
?>
<form method="post" action="<?=$sPage;?>">
    <? $obTabControl->BeginNextTab(); ?>
	<tr>
        <td valign="top" colspan="2"><h3><?=Loc::getMessage('EVA_MOD_NEWS')?></h3></td>
	</tr>
    <tr>
        <td valign="middle" width="30%"><?=Loc::getMessage('EVA_DAYS_NEWS')?></td>
        <td valign="top" width="70%">
            <input type="text"
                   name="options[oldnewsdays]"
                   value="<?=Option::get($mid, 'oldnewsdays', '7') ?>">
        </td>
    </tr>
	<tr>
        <td valign="top" colspan="2"><hr /></td>
	</tr>
    <tr>
        <td valign="top" colspan="2"><h3><?=Loc::getMessage('EVA_MOD_PS')?></h3></td>
    </tr>
    <!-- выводим платежные системы  -->  
    <?php $i=0; ?>
    <?php while ($ptype = $db_ptype->Fetch()): ?>
        <?php if ($ptype['ID']==1) {
            # пропускаем Внутренний счет
            //continue;
        } ?>
        <tr>
        <td valign="middle" width="30%"><?if ($ptype['ID']==1) {?><br><?}?><?=$ptype['NAME'] ?>, ID<?=$ptype['ID']?></td>
        <td valign="top" width="70%">
			<table>
				<?if ($ptype['ID']==1) {?><tr>
					<th><?=Loc::getMessage('EVA_CODE_PS')?></th>
					<th><?=Loc::getMessage('EVA_ONLINE_PS')?>, Y/N</th>
					<th><?=Loc::getMessage('EVA_PS_TAX')?>, %</th>
					<th><?=Loc::getMessage('EVA_SITE_TAX')?>, %</th>
				</tr>
				<?}?>
				<tr>
					<td><input type="text" name="options[paysystemid_<?=$ptype['ID'] ?>]" value="<?=Option::get($mid, 'paysystemid_'.$ptype['ID'], 'ps'.$ptype['ID'].'_code') ?>" size=""></td>
					<td><input type="text" name="options[online_psid_<?=$ptype['ID'] ?>]" value="<?=Option::get($mid, 'online_psid_'.$ptype['ID'], 'N') ?>" size="10"></td>
					<td><input type="text" name="options[tax_psid_<?=$ptype['ID'] ?>]" value="<?=Option::get($mid, 'tax_psid_'.$ptype['ID'], '0') ?>" size="10" disabled></td>
					<td><input type="text" name="options[sitetax_psid_<?=$ptype['ID'] ?>]" value="<?=Option::get($mid, 'sitetax_psid_'.$ptype['ID'], '0') ?>" size="10" disabled></td>
				</tr>
			</table>
        </td>
        </tr>
    <?php endwhile ?>   
    <!-- выводим платежные системы  -->
	
	<tr>
        <td valign="top" colspan="2"><hr /></td>
	</tr>
        <tr>
        <td valign="top" colspan="2"><h3><?=Loc::getMessage('EVA_PAYMENTS')?></h3><?=Loc::GetMessage("EVA_TIMER_WARNING")?></td>
    </tr>
    <tr>
		<td valign="middle" width="30%"><?=Loc::getMessage('EVA_TIMER_SETTING')?></td>
		<td valign="top" width="70%">
			<input type="text" name="options[timer_payments]" value="<?=Option::get($mid, 'timer_payments', '120') ?>">
		</td> 
    </tr>
	<tr>
		<td valign="middle" width="30%"><?=Loc::getMessage('EVA_MODE_AUTO')?></td>
		<td valign="top" width="70%">
			<?$mode = Option::get($mid, 'mode_pay', 'N');?>
			<input type="hidden" id="mode_pay" name="options[mode_pay]" value="<?=$mode?>">
			<input type="checkbox" id="mode_payments" name="mode_payments" value="<?=$mode?>"<?if($mode=="Y") echo " checked";?>>
			<script>
				BX.ready(function(){
					BX.bindDelegate(
						document.body, 'click', {tagName: 'label'},
						function(e){
							if(!e) e = window.event;
							var che = this.getAttribute('for');
							if(BX(che).value == 'Y')
							{
								BX(che).value = 'N';
								BX("mode_pay").value = 'N';
								BX(che).removeAttribute('checked');
							}
							else
							{
								BX(che).value = 'Y';
								BX("mode_pay").value = 'Y';
								BX(che).setAttribute('checked','');
							}

							return BX.PreventDefault(e);
						}
					);
				});
			</script>
		</td>
    </tr>
	<tr>
		<td valign="middle" width="30%"><?=Loc::getMessage('EVA_CHECKTIMER_SETTING')?></td>
		<td valign="top" width="70%">
			<input type="number" name="options[checktimer_payments]" value="<?=Option::get($mid, 'checktimer_payments', '5') ?>">
		</td> 
    </tr>
	
	<tr>
        <td valign="top" colspan="2"><hr /></td>
	</tr>
    <tr>
        <td valign="top" colspan="2"><h3><?=Loc::getMessage('EVA_MOD_TAX')?></h3><?=Loc::GetMessage("EVA_PERCENTS_WARNING")?></td>
    </tr>
	<tr>
        <td valign="middle" width="30%"><br><?=Loc::getMessage('EVA_TAX_IN')?></td>
        <td valign="top" width="70%">
			<table>
				<tr>
					<th><?=Loc::getMessage('EVA_SYSTEM_TAX')?></th>
					<th><?=Loc::getMessage('EVA_AGENT_TAX')?></th>
				</tr>
				<tr>
					<td><input type="number" name="options[tax_in_system]" value="<?=Option::get($mid, 'tax_in_system', '0') ?>" size="10" min="0" max="100" disabled></td>
					<td><input type="number" name="options[tax_in_agent]" value="<?=Option::get($mid, 'tax_in_agent', '0') ?>" size="10" min="0" max="100<?//=Option::get($mid, 'tax_in_system', '100') ?>" disabled></td>
				</tr>
			</table>
        </td>
    </tr>
	<tr>
        <td valign="middle" width="30%"><?=Loc::getMessage('EVA_TAX_INVEST')?></td>
        <td valign="top" width="70%">
			<table>
				<tr>
					<td><input type="number" name="options[tax_invest_system]" value="<?=Option::get($mid, 'tax_invest_system', '0') ?>" size="10" min="0" max="100" disabled></td>
					<td><input type="number" name="options[tax_invest_agent]" value="<?=Option::get($mid, 'tax_invest_agent', '0') ?>" size="10" min="0" max="100<?//=Option::get($mid, 'tax_invest_system', '100') ?>" disabled></td>
				</tr>
			</table>
        </td>
    </tr>
	<tr>
        <td valign="middle" width="30%"><?=Loc::getMessage('EVA_TAX_IS')?></td>
        <td valign="top" width="70%">
			<table>
				<tr>
					<td><input type="number" name="options[tax_is_system]" value="<?=Option::get($mid, 'tax_is_system', '0') ?>" size="10" min="0" max="100" disabled></td>
					<td><input type="number" name="options[tax_is_agent]" value="<?=Option::get($mid, 'tax_is_agent', '0') ?>" size="10" min="0" max="100<?//=Option::get($mid, 'tax_is_system', '100') ?>" disabled></td>
				</tr>
			</table>
        </td>
    </tr>
	<tr>
        <td valign="middle" width="30%"><?=Loc::getMessage('EVA_TAX_PROFIT')?></td>
        <td valign="top" width="70%">
			<table>
				<tr>
					<td><input type="number" name="options[tax_profit_system]" value="<?=Option::get($mid, 'tax_profit_system', '0') ?>" size="10" min="0" max="100"></td>
					<td><input type="number" name="options[tax_profit_agent]" value="<?=Option::get($mid, 'tax_profit_agent', '0') ?>" size="10" min="0" max="100<?//=Option::get($mid, 'tax_profit_system', '100') ?>"></td>
				</tr>
			</table>
        </td>
    </tr>
	<tr>
        <td valign="middle" width="30%"><?=Loc::getMessage('EVA_TAX_OUT')?></td>
        <td valign="top" width="70%">
			<table>
				<tr>
					<td><input type="number" name="options[tax_out_system]" value="<?=Option::get($mid, 'tax_out_system', '0') ?>" size="10" min="0" max="100"></td>
					<td><input type="number" name="options[tax_out_agent]" value="<?=Option::get($mid, 'tax_out_agent', '0') ?>" size="10" min="0" max="100<?//=Option::get($mid, 'tax_out_system', '100') ?>"></td>
				</tr>
			</table>
        </td>
    </tr>
	<tr>
        <td valign="middle" width="30%"><?=Loc::getMessage('EVA_TAX_PROJECT')?></td>
        <td valign="top" width="70%">
			<table>
				<tr>
					<td><input type="number" name="options[tax_project_system]" value="<?=Option::get($mid, 'tax_project_system', '0') ?>" size="10" min="0" max="100"></td>
					<td><input type="number" name="options[tax_project_agent]" value="<?=Option::get($mid, 'tax_project_agent', '0') ?>" size="10" min="0" max="100<?//=Option::get($mid, 'tax_project_system', '100') ?>"></td>
				</tr>
			</table>
        </td>
    </tr>
 
	<?$obTabControl->BeginNextTab();?>
		<?require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/admin/group_rights.php");?>

    <? $obTabControl->Buttons(); ?>
<div id="tabButtons">
    <input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="submit" name="Update" value=" <?=Loc::getMessage('MAIN_SAVE'); ?>" title="<?=Loc::getMessage('MAIN_OPT_SAVE_TITLE'); ?>">
    <input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="submit" name="Apply" value="<?=Loc::getMessage('MAIN_OPT_APPLY'); ?>" title="<?=Loc::getMessage('MAIN_OPT_APPLY_TITLE'); ?>">
    <? if (strlen($_REQUEST['back_url_settings']) > 0): ?>
        <input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="button" name="Cancel" value="<?=Loc::getMessage('MAIN_OPT_CANCEL'); ?>" title="<?=Loc::getMessage('MAIN_OPT_CANCEL_TITLE'); ?>" onclick="window.location='<?=htmlspecialcharsbx(CUtil::addslashes($_REQUEST['back_url_settings'])); ?>'">
        <input type="hidden" name="back_url_settings" value="<?=htmlspecialcharsbx($_REQUEST['back_url_settings']) ?>">
    <? endif; ?>
    <input <?if ($POST_RIGHT<"W") echo "disabled" ?> type="submit" name="RestoreDefaults" title="<?=Loc::getMessage('MAIN_HINT_RESTORE_DEFAULTS'); ?>" OnClick="return confirm('<?=AddSlashes(Loc::getMessage('MAIN_HINT_RESTORE_DEFAULTS_WARNING')); ?>')" value="<?=Loc::getMessage('MAIN_RESTORE_DEFAULTS'); ?>">
    <?=bitrix_sessid_post(); ?>
    <input type="hidden" name="lang" value="<?=LANGUAGE_ID; ?>">
</div>
    <? $obTabControl->End(); ?>
</form>
<?
if(strlen($_REQUEST["tabControl"]) > 0)
{
?>
<script>
	tabControl.SelectTab('<?echo htmlspecialcharsbx($_REQUEST["tabControl"])?>');
	BX('tabControl_active_tab').value = '<?echo htmlspecialcharsbx($_REQUEST["tabControl"])?>';
	
	BX.ready( function(){
		var obTabs = BX('tabButtons');
		/*console.log(BX('tabControl_active_tab').value);*/
	} );
</script>
<?
}
Loader::includeModule('eva.administration');
?>