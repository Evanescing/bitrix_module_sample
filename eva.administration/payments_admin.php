<?
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ExpressionField;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Loader,
	Bitrix\Sale;
use Eva\Administration\Scripts,
	Eva\Administration\Tables;

$mid = "eva.administration";

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php"); // первый общий пролог

require_once($_SERVER["DOCUMENT_ROOT"]."/local/modules/".$mid."/include.php"); // инициализация модуля
require_once($_SERVER["DOCUMENT_ROOT"]."/local/modules/".$mid."/prolog.php"); // пролог модуля
echo '<link rel="stylesheet" type="text/css" href="/local/modules/'.$mid.'/styles.css" />';

global $APPLICATION, $USER, $USER_FIELD_MANAGER;
$isAdmin = $USER->CanDoOperation('edit_php');

IncludeModuleLangFile(__FILE__);

if (!CModule::IncludeModule(ADMIN_MODULE_NAME))
{
	$APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}

// функции работы с платежами
if(
	$_SERVER['REQUEST_METHOD'] == 'POST'
	&& $_POST["ajax"] === "y"
)
{
	\CUtil::JSPostUnescape();
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_js.php");

	if(
		intVal($_POST['id_payment']) > 0
		&& $isAdmin
		&& check_bitrix_sessid()
	)
	{
		$id_payment = intVal($_POST['id_payment']);

		if($_POST['action'] == 'checkPayment')
		{
			if($id_payment > 0)
			{
				printf('<div class="adm-info-message">');
				$checkResult = Scripts::checkPayment($id_payment);
				if(count($checkResult) > 0)
				{
					printf($checkResult[0]);
				}
				else
					printf(str_replace('#ID#',$id_payment, GetMessage("EVA_CHECKPAYMENT_SUCCESS")));
				printf('</div>');
			}
			else
			{
				printf('<div class="adm-info-message">');
				printf(GetMessage("EVA_EMPTYPAYMENT_ERROR"));
				printf('</div>');
			}
		}
		// ...some else actions
		
	}

	require($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/include/epilog_admin_js.php");
	die();
}

// get entity settings
$hlblock = null;
$ENTITY_ID = 6;
if ($ENTITY_ID > 0)
{
	$hlblock = HL\HighloadBlockTable::getById($ENTITY_ID)->fetch();

	if (!empty($hlblock))
	{
		//check rights
		$canEdit = false;
		$canDelete = false;
		
		if ($USER->isAdmin())
		{
			$canEdit = false;
			$canDelete = false;
		}
	}
}

if (empty($hlblock))
{
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

	echo GetMessage('HLBLOCK_ADMIN_ROWS_LIST_NOT_FOUND');

	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");

	die;
}

$entity = HL\HighloadBlockTable::compileEntity($hlblock);

/** @var HL\DataManager $entity_data_class */
$entity_data_class = $entity->getDataClass();
$entity_table_name = $hlblock['TABLE_NAME'];

$sTableID = 'tbl_'.$entity_table_name;
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

$arHeaders = array(array(
	'id' => 'ID',
	'content' => 'ID',
	'sort' => 'ID',
	'default' => true
));

$ufEntityId = 'HLBLOCK_'.$hlblock['ID'];
$USER_FIELD_MANAGER->AdminListAddHeaders($ufEntityId, $arHeaders);

// show all columns by default
foreach ($arHeaders as &$arHeader)
{
	$arHeader['default'] = true;
}
unset($arHeader);

$lAdmin->AddHeaders($arHeaders);

if (!in_array($by, $lAdmin->GetVisibleHeaderColumns(), true))
{
	$by = 'ID';
}

// add filter
$filter = null;

$filterFields = array('find_id');
$filterValues = array();
$filterTitles = array('ID');

$USER_FIELD_MANAGER->AdminListAddFilterFields($ufEntityId, $filterFields);

$filter = $lAdmin->InitFilter($filterFields);

if (!empty($find_id))
{
	$filterValues['ID'] = $find_id;
}

$USER_FIELD_MANAGER->AdminListAddFilter($ufEntityId, $filterValues);
$USER_FIELD_MANAGER->AddFindFields($ufEntityId, $filterTitles);

// group actions
if($lAdmin->EditAction() && $canEdit)
{
	foreach($FIELDS as $ID=>$arFields)
	{
		$ID = (int)$ID;
		if ($ID <= 0)
			continue;

		if(!$lAdmin->IsUpdated($ID))
			continue;

		$entity_data_class::update($ID, $arFields);
	}
}

if($arID = $lAdmin->GroupAction())
{
	if($_REQUEST['action_target']=='selected')
	{
		$arID = array();

		$rsData = $entity_data_class::getList(array(
			"select" => array('ID'),
			"filter" => $filterValues
		));

		while($arRes = $rsData->Fetch())
			$arID[] = $arRes['ID'];
	}

	foreach ($arID as $ID)
	{
		$ID = (int)$ID;

		if (!$ID)
		{
			continue;
		}

		switch($_REQUEST['action'])
		{
			case "delete":
				if ($canDelete)
				{
					$entity_data_class::delete($ID);
				}
				break;
		}
	}
}

$arr = $canDelete ? array('delete' => true) : array();
$lAdmin->AddGroupActionTable($arr);


//Статусы платежей
//...some ids for github sample
$doneStatus = 12345;
$inCancelStatus = 1234;
$inCreateStatus = 123;
//Типы платежей
//...
//Назначения платежей
$popolnenie = $vyvod = 0;
$arDataPurpose = Scripts::getDataListEnumFields(85);//TODO module constant UF_PURPOSE
/*
purpose_1 Пополнение
purpose_2 Вывод
...
*/
foreach($arDataPurpose as $dataPurpose)
{
	if($dataPurpose['XML_ID'] == 'purpose_1')
		$popolnenie = $dataPurpose['ID'];
	else if($dataPurpose['XML_ID'] == 'purpose_2')
		$vyvod = $dataPurpose['ID'];	
}

// select data
/** @var string $order */
$order = strtoupper($order);

$usePageNavigation = true;
$usePageNavigation = false; // temporary
if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'excel')
{
	$usePageNavigation = false;
}
else
{
	$navyParams = CDBResult::GetNavParams(CAdminResult::GetNavSize(
		$sTableID,
		array('nPageSize' => 20, 'sNavID' => $APPLICATION->GetCurPage().'?tabControl=eva_payments_in&ENTITY_ID='.$ENTITY_ID)
	));
	if ($navyParams['SHOW_ALL'])
	{
		$usePageNavigation = false;
	}
	else
	{
		$navyParams['PAGEN'] = (int)$navyParams['PAGEN'];
		$navyParams['SIZEN'] = (int)$navyParams['SIZEN'];
	}
}
$selectFields = $lAdmin->GetVisibleHeaderColumns();
if (!in_array('ID', $selectFields))
	$selectFields[] = 'ID';
$getListParams = array(
	'select' => $selectFields,
	'filter' => $filterValues,
	'order' => array($by => $order)
);
unset($filterValues, $selectFields);
if ($usePageNavigation)
{
	$getListParams['limit'] = $navyParams['SIZEN'];
	$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
}

if ($usePageNavigation)
{
	$countQuery = new Query($entity_data_class::getEntity());
	$countQuery->addSelect(new ExpressionField('CNT', 'COUNT(1)'));
	$countQuery->setFilter($getListParams['filter']);
	$totalCount = $countQuery->setLimit(null)->setOffset(null)->exec()->fetch();
	unset($countQuery);
	$totalCount = (int)$totalCount['CNT'];
	if ($totalCount > 0)
	{
		$totalPages = ceil($totalCount/$navyParams['SIZEN']);
		if ($navyParams['PAGEN'] > $totalPages)
			$navyParams['PAGEN'] = $totalPages;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = $navyParams['SIZEN']*($navyParams['PAGEN']-1);
	}
	else
	{
		$navyParams['PAGEN'] = 1;
		$getListParams['limit'] = $navyParams['SIZEN'];
		$getListParams['offset'] = 0;
	}
}

$rsData = new CAdminResult($entity_data_class::getList($getListParams), $sTableID);
if ($usePageNavigation)
{
	$rsData->NavStart($getListParams['limit'], $navyParams['SHOW_ALL'], $navyParams['PAGEN']);
	$rsData->NavRecordCount = $totalCount;
	$rsData->NavPageCount = $totalPages;
	$rsData->NavPageNomer = $navyParams['PAGEN'];
}
else
{
	$rsData->NavStart();
}

// build list
$lAdmin->NavText($rsData->GetNavPrint(GetMessage("PAGES")));
while($arRes = $rsData->NavNext(true, "f_"))
{
	$row = $lAdmin->AddRow($f_ID, $arRes);
	if(intval($f_UF_FROM_USER) > 0)
		$row->AddViewField("UF_FROM_USER", '<a href="'.htmlspecialcharsbx('user_edit.php?lang='.LANGUAGE_ID.'&ID='.$f_UF_FROM_USER).'" target="_blank">'.$f_UF_FROM_USER.'</a>');
	if(intval($f_UF_TO_USER) > 0)
		$row->AddViewField("UF_TO_USER", '<a href="'.htmlspecialcharsbx('user_edit.php?lang='.LANGUAGE_ID.'&ID='.$f_UF_TO_USER).'" target="_blank">'.$f_UF_TO_USER.'</a>');
	if(intval($f_UF_FROM_PS) > 0)
		$row->AddViewField("UF_FROM_PS", Scripts::getNameEnumFields($f_UF_FROM_PS));
	if(intval($f_UF_TO_PS) > 0)
		$row->AddViewField("UF_TO_PS", Scripts::getNameEnumFields($f_UF_TO_PS));
	if(intval($f_UF_TYPE) > 0)
		$row->AddViewField("UF_TYPE", Scripts::getNameEnumFields($f_UF_TYPE));
	if(intval($f_UF_PURPOSE) > 0)
		$row->AddViewField("UF_PURPOSE", Scripts::getNameEnumFields($f_UF_PURPOSE));
	if(intval($f_UF_STATUS) > 0)
		$row->AddViewField("UF_STATUS", Scripts::getNameEnumFields($f_UF_STATUS));
	if(intval($f_UF_ORDER_ID) > 0)
		$row->AddViewField("UF_ORDER_ID", '<a href="'.htmlspecialcharsbx('sale_order_view.php?lang='.LANGUAGE_ID.'&ID='.$f_UF_ORDER_ID).'" target="_blank">'.$f_UF_ORDER_ID.'</a>');
	if(intval($f_UF_PROJID) > 0)
		$row->AddViewField("UF_PROJID", '<a href="'.htmlspecialcharsbx('iblock_element_edit.php?IBLOCK_ID=1&type=catalog&ID='.$f_UF_PROJID.'&lang='.LANGUAGE_ID).'" target="_blank">'.$f_UF_PROJID.'</a>');
	
	if(intVal($f_UF_HAS_ERROR) == 1)
		$row->AddViewField("UF_HAS_ERROR", "error");
	
	if ($canEdit)
	{
		$USER_FIELD_MANAGER->AddUserFields('HLBLOCK_'.$hlblock['ID'], $arRes, $row);
	}

	$arActions = array();
	
	//Проверка платежа только для раздела (статуса) Проведено
	$action = '';
	if(intval($f_UF_STATUS) == $doneStatus)
	{
		?><script>
		if(typeof setErrorStyle !== 'undefined')
		{
			setErrorStyle('<?=$f_ID?>');
		}
		</script><?// подсветка для ошибочных платежей
		
		$action = 'checkPaymentAction("'.$f_ID.'");return false;';
	}
	
	//статус не Отменен и не Создан (еще не в обработке)
	if(intval($f_UF_STATUS) != $inCancelStatus || intval($f_UF_STATUS) != $inCreateStatus)
	{
		if(strlen($action) > 0)
			$arActions[] = array(
				'ICON' => 'copy',
				'TEXT' => GetMessage('EVA_ADMIN_MENU_CHECK'),
				'ACTION' => $action,
				'DEFAULT' => false
			);
	}
	
	if ($canEdit)
	{
		$arActions[] = array(
			'ICON' => 'copy',
			'TEXT' => GetMessage('MAIN_ADMIN_MENU_COPY'),
			'ACTION' => $lAdmin->ActionRedirect('settings.php?mid=eva.administration&tabControl=eva_payments_in&ENTITY_ID='.$hlblock['ID'].'&ID='.$f_ID.'&lang='.LANGUAGE_ID.'&action=copy')
		);
	}
	if ($canDelete)
	{
		$arActions[] = array(
			'ICON'=>'delete',
			'TEXT' => GetMessage('MAIN_ADMIN_MENU_DELETE'),
			'ACTION' => 'if(confirm(\''.GetMessageJS('HLBLOCK_ADMIN_DELETE_ROW_CONFIRM').'\')) '.
				$lAdmin->ActionRedirect('settings.php?mid=eva.administration&action=delete&tabControl=eva_payments_in&ENTITY_ID='.$hlblock['ID'].'&ID='.$f_ID.'&lang='.LANGUAGE_ID.'&'.bitrix_sessid_get())
		);
	}

	$row->AddActions($arActions);
}


// view
$menu = array();
if ($canEdit)
{
	$menu[] = array(
		'TEXT'	=> GetMessage('HLBLOCK_ADMIN_ROWS_ADD_NEW_BUTTON'),
		'TITLE'	=> GetMessage('HLBLOCK_ADMIN_ROWS_ADD_NEW_BUTTON'),
		'LINK'	=> 'settings.php?mid=eva.administration&tabControl=eva_payments_in&ENTITY_ID='.$ENTITY_ID.'&amp;lang='.LANGUAGE_ID,
		'ICON'	=> 'btn_new'
	);
}
//$lAdmin->AddAdminContextMenu($menu);

$lAdmin->CheckListMode();
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$filter = new CAdminFilter(
	$sTableID."_filter_id",
	$filterTitles
);
?>
<style>
.adm-error-payment-css,
.adm-list-table-row.adm-error-payment-css .adm-list-table-cell {
	background-color: #f9adad!important;
}
</style>
<script>
function providePayment(ids, action, comment=''){
	ShowWaitWindow();
	BX.ajax.post(
		'payments_admin.php?lang=' + phpVars.LANGUAGE_ID + '&sessid=' + phpVars.bitrix_sessid,
		{
			id_payment: ids,
			provide_comment: comment,
			payaction: action,
			ajax: 'y'
		},
		function(result){
			document.getElementById('result_div').innerHTML = result;
			CloseWaitWindow();
		}
	);
}
</script>
<form name="find_form" method="GET" action="<?echo $APPLICATION->GetCurPage()?>">
<?
	$filter->Begin();
	?>
	<tr>
		<td>ID</td>
		<td><input type="text" name="find_id" size="47" value="<?echo htmlspecialcharsbx($find_id)?>"><?=ShowFilterLogicHelp()?></td>
	</tr>
	<?
	$USER_FIELD_MANAGER->AdminListShowFilter($ufEntityId);
	$filter->Buttons(array("table_id"=>$sTableID, "url"=>$APPLICATION->GetCurPage(), "form"=>"find_form"));
	$filter->End();
?>
</form>

<div id="result_div"></div>
<?

$lAdmin->DisplayList();

?>
<div id="restSumText" style="display: none;"></div>
<script>
function checkPaymentAction(ids)
{
	BX.ajax.post(
		'payments_admin.php?lang=' + phpVars.LANGUAGE_ID + '&sessid=' + phpVars.bitrix_sessid,
		{
			id_payment: ids,
			action: 'checkPayment',
			ajax: 'y',
			async: true
		},
		function(result){
			document.getElementById("result_div").innerHTML = '<div>'+result+'</div>';
		}
	);
}

function getSumRest(ids, getRestSum)
{
	var restSumText = '';
	if(getRestSum == 'Y')
	{
		BX.ajax.post(
			'payments_admin.php?lang=' + phpVars.LANGUAGE_ID + '&sessid=' + phpVars.bitrix_sessid,
			{
				id_payment: ids,
				action: 'getRestSum',
				ajax: 'y',
				async: true
			},
			function(result){
				document.getElementById("restSumText").innerHTML = '<div>'+result+'<br>&nbsp;</div>';
				var x = document.getElementById("restSumText").innerHTML;
				showForm(ids, getRestSum);
			}
		);
	}
	else
	{
		showForm(ids, getRestSum);
	}
}
function showForm(ids, getRestSum)
{
	var restSumText = '';
	if(getRestSum == 'Y')
		restSumText = document.getElementById("restSumText").innerHTML;
	else
		document.getElementById("restSumText").innerHTML = '';
	
	var btn_save = {
		title: '<?=GetMessage("EVA_PAYMENT_PROVIDE")?>',
		id: 'provide_submit',
		name: 'provide_submit',
		className: 'adm-btn-save',
		action: function () {
			
			document.getElementById('tbl_smpayments_filter_idset_filter').click();
			this.parentWindow.Close();
			return false;
		}
	};
	var btn_cancel = {
		title: '<?=GetMessage("EVA_PAYMENT_CANCEL")?>',
		id: 'provide_cancel',
		name: 'provide_cancel',
		className: 'adm-btn-save',
		action: function () {
			// ... some code
			document.getElementById('tbl_smpayments_filter_idset_filter').click();
			this.parentWindow.Close();
			return false;
		}
	};
	var Dialog = new BX.CDialog({
	   title: "<?=GetMessage("PROVIDE_PAYMENT")?>",
	   head: "<?=GetMessage("PROVIDE_PAYMENT")?> N"+ids,
	   content: restSumText+'<form id="provide_form" name="provide_form" class="" method="POST" action="<?echo $APPLICATION->GetCurPage()?>"><input type="hidden" id="f_id" value="'+ids+'"><textarea id="provide_comment" placeholder="<?=GetMessage("EVA_PAYMENT_COMMENT")?>" style="width: 97%;height: 100px;"></textarea></form>',
	   icon: 'head-block',
	   resizable: false,
	   draggable: true,
	   height: '250',
	   width: '400',
	   buttons: [btn_save, btn_cancel, BX.CDialog.btnClose]
	});
	Dialog.Show();
	return false;
}

function setErrorStyle(ids)
{
	var tableObj = BX("tbl_smpayments");
	var arItems = BX.findChildren(tableObj, {'tag':'tr', 'class':'adm-list-table-row'}, true);
	
	if (!arItems)
		arItems = [];

	for (var i = 0; i < arItems.length; i++)
	{
		var arItemsTd = BX.findChildren(arItems[i], {'tag':'td', 'class':'adm-list-table-cell'}, true);
		if (arItemsTd)
		{
			for (var j = 0; j < arItemsTd.length; j++)
			{
				if(arItemsTd[j].innerHTML == 'error')
				{
					if(!BX.hasClass(arItems[i], 'adm-error-payment-css'))
						BX.addClass(arItems[i], 'adm-error-payment-css');
				}
				else
				{
					if(BX.hasClass(arItems[i], 'adm-error-payment-css'))
						BX.removeClass(arItems[i], 'adm-error-payment-css');
				}
			}
		}
	}
	
}
</script>
<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>