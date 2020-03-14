<?
/** @global CUser $USER
 * @global CMain $APPLICATION
 * @global CAdminMenu $adminMenu */
use \Bitrix\Main\EventManager;
use \Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

$eventManager = EventManager::getInstance();
$eventManager->addEventHandler(
    'main',
    'OnBuildGlobalMenu',
    'rsOnBuildGlobalMenuHandler'
);

function rsOnBuildGlobalMenuHandler(&$arGlobalMenu, &$arModuleMenu) {
    global $APPLICATION;
    
    $moduleId = 'eva.administration';
    
    if ($APPLICATION->GetGroupRight($moduleId) >= 'R') {
        
        $arGlobalMenu[] = array(
            'menu_id' => 'eva',
            'sort' => 1000,
            "text" => "eva admin",
			"title" => "eva module settings",
            'icon' => 'sale_menu_icon_crm',
            'items_id' => 'menu_eva',
			"module_id" => "eva.administration",
            'items' => array(
                array(
                    "parent_menu" => "global_menu_eva",
					"sort" => 100,
					"text" => "eva Administration",
					"title" => "eva module settings",
					"icon" => "sale_menu_icon_crm",
					"page_icon" => "sale_page_icon_crm",
					"module_id" => "eva.administration",
					"items" => array(
						array(
							"text" => GetMessage("EVA_MENU_SETTINGS"),
							"url"  => "settings.php?lang=".LANGUAGE_ID."&mid=eva.administration&mid_menu=1",
							"icon" => "sale_menu_icon",
							"page_icon" => "iblock_page_icon_iblocks",
							"module_id" => "eva.administration",
							"more_url"  => array(),
							"title" => GetMessage("EVA_MENU_SETTINGS")
						),
					),//items
                ),
				array(
						"sort" => "100",
						"text" => GetMessage("EVA_MENU_PROJECTS"),
						"url"  => "iblock_element_admin.php?IBLOCK_ID=1&type=catalog&lang=".LANGUAGE_ID."&find_el_y=Y",
						"icon" => "blog_menu_icon",
						"page_icon" => "blog_menu_icon",
						"module_id" => "eva.administration",
						"more_url"  => array(),
						"title" => GetMessage("EVA_MENU_PROJECTS")
					),
					array(
						"sort" => "200",
						"text" => GetMessage("EVA_MENU_ORDERS"),
						"url"  => "iblock_element_admin.php?IBLOCK_ID=5&type=catalog&lang=".LANGUAGE_ID."&find_el_y=Y",
						"icon" => "blog_menu_icon",
						"page_icon" => "blog_menu_icon",
						"module_id" => "eva.administration",
						"more_url"  => array(),
						"title" => GetMessage("EVA_MENU_ORDERS")
					),
					array(
						"sort" => "300",
						"text" => GetMessage("EVA_MENU_BILL"),
						"url"  => "payments_admin.php?lang=".LANGUAGE_ID."&set_filter=Y&adm_filter_applied=0&find_UF_STATUS[0]=19&by=ID&order=desc",//"settings.php?mid=eva.administration&tabControl=eva_payments_in&lang=".LANGUAGE_ID,
						"icon" => "update_menu_icon",
						"page_icon" => "update_menu_icon",
						"module_id" => "eva.administration",
						"more_url"  => array(
							"payments_admin.php?lang=".LANGUAGE_ID."&set_filter=Y&adm_filter_applied=0&find_UF_STATUS[0]=19&by=ID&order=desc",
							"payments_admin.php?lang=".LANGUAGE_ID
						),
						"title" => GetMessage("EVA_MENU_BILL")
					),
					array(
						"sort" => "400",
						"text" => GetMessage("EVA_MENU_AGENTS"),
						"url"  => "sale_affiliate.php?lang=".LANGUAGE_ID,
						"icon" => "user_menu_icon",
						"page_icon" => "user_menu_icon",
						"module_id" => "eva.administration",
						"more_url"  => array(
							"sale_affiliate.php?lang=".LANGUAGE_ID
						),
						"title" => GetMessage("EVA_MENU_AGENTS")
					),
					array(
						"sort" => "500",
						"text" => GetMessage("EVA_MENU_PS"),
						"url"  => "sale_pay_system.php?lang=".LANGUAGE_ID,
						"icon" => "currency_menu_icon",
						"page_icon" => "currency_menu_icon",
						"module_id" => "eva.administration",
						"more_url"  => array(
							"sale_pay_system.php?lang=".LANGUAGE_ID
						),
						"title" => GetMessage("EVA_MENU_PS")
					),
					array(
						"sort" => "600",
						"text" => GetMessage("EVA_MENU_MESSAGES"),
						"url"  => "message_admin.php?lang=".LANGUAGE_ID,
						"icon" => "mail_menu_icon",
						"page_icon" => "mail_menu_icon",
						"module_id" => "eva.administration",
						"more_url"  => array(
							"message_admin.php?lang=".LANGUAGE_ID
						),
						"title" => GetMessage("EVA_MENU_MESSAGES")
					)
            )
        );
    }
}