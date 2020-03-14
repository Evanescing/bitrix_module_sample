<?php
namespace Eva\Administration;
use Bitrix\Main\Config;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;
use Bitrix\Sale;
//	Bitrix\Sale\PaySystem;
use Eva\Administration\Tables;

class Scripts
{
	public static function getOption($optionName)
    {
        $value = Config\Option::get("eva.administration", $optionName);
        return $value;
    }
	
	public static function getNewsDays()
    {
        $oldnewsdays = Config\Option::get("eva.administration", 'oldnewsdays');
        return $oldnewsdays;
    }
	
	public static function setNewsDays($val)
    {
        Config\Option::set("eva.administration", 'oldnewsdays', $val);
    }
	
	public static function getPaymentSystemName($id)
    {
		if(Loader::includeModule("sale"))
		{
			$arPStype = array();
			$obPStype = \CSalePaySystem::GetList($arOrder = Array("SORT"=>"ASC", "ID"=>"ASC"), Array("LID"=>SITE_ID, 'ID'=>$id));
			while($arPS = $obPStype->Fetch())
			{
				return $arPS['NAME'];
			}
			return '';
		}
		else
			return 'Module sale is not used';
    }
	
	public static function getPaymentSystems($typeId=0)
    {
		if(Loader::includeModule("sale"))
		{
			$arPStype = array();
			$arFilter = array();
			$arFilter["LID"] = SITE_ID;
			if($typeId > 0)
				$arFilter["PERSON_TYPE_ID"] = $typeId;
			$obPStype = \CSalePaySystem::GetList($arOrder = Array("SORT"=>"ASC", "ID"=>"ASC"), $arFilter);
			while($arPS = $obPStype->Fetch())
			{
				$arPStype[] = $arPS;
			}
			return $arPStype;
		}
		else
			return 'Module sale is not used';
    }
	
	public static function getPaymentSystemsList()
    {
		$psList = $listItems = array();
		$psList = self::getPaymentSystems();
		if(is_array($psList))
		{
			foreach($psList as $ps)
			{
				$listItems['ps_'.$ps['ID']] = $ps['NAME'];
			}
		}
		return $listItems;
    }
	
	public static function getIdEnumFields($USER_FIELD_ID, $xmlId)
	{
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"USER_FIELD_ID" => $USER_FIELD_ID,
			"XML_ID" => $xmlId,
		));
		if($arEnum = $rsEnum->Fetch())
		{
			return $arEnum['ID'];
		}
		else
			return false;
	}
	
	//значение элемента списка пользовательского поля
	public static function getNameEnumFields($id)
	{
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $id,
		));
		if($arEnum = $rsEnum->Fetch())
		{
			return $arEnum['VALUE'];
		}
		else
			return false;
	}
	
	//данные значения списка пользовательского поля
	public static function getDataEnumFields($id)
	{
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"ID" => $id,
		));
		if($arEnum = $rsEnum->Fetch())
		{
			return $arEnum;
		}
		else
			return false;
	}
	
	//все данные пользовательского поля
	public static function getDataListEnumFields($USER_FIELD_ID)
	{
		$arResult = array();
		$rsEnum = \CUserFieldEnum::GetList(array(), array(
			"USER_FIELD_ID" => $USER_FIELD_ID,
		));
		while($arEnum = $rsEnum->GetNext())
		{
			$arResult[$arEnum['ID']] = $arEnum;
		}
		return $arResult;
	}
	
	public static function updateEnumPaymentSystems($USER_FIELD_ID)
	{
		if($USER_FIELD_ID == intVal(self::getOption('pay_from_prop_id')) || $USER_FIELD_ID == intVal(self::getOption('pay_to_prop_id')))
		{
			$arSystems = self::getPaymentSystemsList();
			$obEnum = new \CUserFieldEnum;
			$arrNewValues = array();
			$i = 0;
			foreach($arSystems as $ids=>$system)
			{
				$rsEnum = \CUserFieldEnum::GetList(array(), array(
					"USER_FIELD_ID" => $USER_FIELD_ID,
					"XML_ID" => $ids,
				));
				if($arEnum = $rsEnum->Fetch())
				{
					$arrNewValues[$arEnum['ID']] = array(
						'XML_ID' => $ids,
						'VALUE' => $system
					);
				}
				else
				{
					$arrNewValues['n'.$i] = array(
						'XML_ID' => $ids,
						'VALUE' => $system,
						'USER_FIELD_ID' => $USER_FIELD_ID,
						'DEF' => 'N',
						'SORT' => 500
					);
					$i++;
				}


			}
			$obEnum->SetEnumValues($USER_FIELD_ID, $arrNewValues);
			return true;
		}
		return false;
	}
	
	/*
	* Рассчитать сумму процента от полной суммы
	* для переводов, выводов на внешние платежные системы
	* берется текущий процент на данный момент рассчетов
	*/
	public static function countSystemTaxes($fullSum, $optionName)
	{
		$percent = self::getOption($optionName);
		if(floatVal($percent <= 0))
			$percent = 0;
		if($fullSum > 0.01 && $percent > 0)
		{
			$sum = (floatVal($fullSum)*$percent)/100;
			$sum = \floor($sum*100)/100; //округление вниз
			if($sum < 0.01)
				$sum = 0.01;

			return $sum;
		}
		return false;
	}
	
	/**
	* Функция Проценты системы
	* в административной части формируется платёж с назначением Процент
	* $fromUserID - От кого, айди юзера
	* $fromPayment - связанный с платежём
	* $fullSum - от какой суммы считаем
	* $optionName - от какого свойства берем процент
	* 	...etc.
	* 	*secured by author policy *
	* $productID - по какому товару, если указан
	* $outer > 0 = платежная система внешняя
	**/
	public static function createSystemTaxes($fromUserID, $fromPayment, $fullSum, $optionName, $productID=0, $outer=0)
	{
		$fromPayment = intVal($fromPayment);
		$fromUserID = intVal($fromUserID);
		
		$percent = self::getOption($optionName);
		if(floatVal($percent <= 0))
			$percent = 0;
		if($fullSum > 0.01 && $percent > 0 && $fromPayment > 0 && $fromUserID > 0)
		{
			$sum = (floatVal($fullSum)*$percent)/100;
			$sum = \floor($sum*100)/100; //округление вниз
			if($sum < 0.01)
				$sum = 0.01;
			
			$curDateTime = new \Bitrix\Main\Type\DateTime();
			$arToPayment = array(
				'UF_FROM_PS' => Scripts::getIdEnumFields(self::getOption('pay_from_prop_id'), 'ps_1'),//Внутренний счет
				'UF_FROM_USER' => $fromUserID,
				'UF_TO_PS' => Scripts::getIdEnumFields(self::getOption('pay_to_prop_id'), 'ps_1'),//Внутренний счет
				'UF_TO_USER' => 0,
				'UF_SUM' => $sum,
				'UF_TIMER_TIME' => $curDateTime,
				'UF_PROJID' => $productID,
				'UF_PAY_CHAIN' => $fromPayment,
				'UF_TAX_PERCENT' => $percent
			);
			$bResult = Tables\PaymentsTable::add($arToPayment);
			$ID = $bResult->getId();
			if($ID > 0 && Loader::includeModule("sale"))
			{
				// some secured code ...
				global $USER;
				if(!$USER) 
					$USER = new \CUser;
					
				$comment = 'Providing Error '.$optionName;
				
				$resIdAccount = self::UpdateAccountEva($fromUserID, -$sum, 'RUB', "Процент системы", 0, "Тип процента ".$optionName);
				if(intVal($resIdAccount) > 0 && Tables\PaymentsTable::update($ID, array('UF_TRANSACT_ID' => intVal($resIdAccount))))
				{
					// some code...
					return $ID;
				}
				else
				{
					// some code for error...
				}
			}
			
		}
		return false;
	}
	
	/**
	* оплачивать заказ при проведении платежа Пополнение
	* для автоматического пополнения счета необходимо разрешать отгрузку при плате и переводить отгрузку в статус Отгружено
	**/
	public static function payinPayOrder($orderId, $paymentId=0)
    { 
		if(Loader::includeModule("sale") && Loader::IncludeModule('catalog'))
		{
			global $USER;
			if(!$USER) 
				$USER = new \CUser;
			$addF = array();
			if($paymentId)
				$addF = array("PAY_VOUCHER_NUM" => $paymentId);
			if(\CSaleOrder::PayOrder($orderId, "Y", true, false, 0, $addF))// true - снимать деньги с внутреннего счета
			{
				//заказ оплачен
				return true;
			}
			
		}
		return false;
	}
	
	/**
	*
	*/
	public static function showProgress($i, $iCount = 100, $iPrecision = 0)
    {
        self::dump(\round($i * 100 / $iCount, $iPrecision) . '%');
    }

    /**
     *
     * Преобразование мобильного номера в десятизначный
     * @param $sPhone
     * @return string
     */
    public static function parseMobilePhone($sPhone)
    {
        $sPhone = preg_replace('/[^\d]/', '', $sPhone);

        if (!$sPhone) {
            return '';
        }

        if (strlen($sPhone) == 11 && ($sPhone[0] == '8' || $sPhone[0] == '7')) {
            $sPhone = substr($sPhone, 1);
        }

        return strlen($sPhone) == 10 ? $sPhone : '';
    }

    /**
     * Применение маски для номера телефона
     * @param $sPhone
     * @param string $sMask
     * @return mixed|string
     */
    public static function applyMaskPhone($sPhone, $sMask = '')
    {
        if ($sPhone && strpos($sPhone, '*') === false) {
            $arPhone = str_split(preg_replace("/[^0-9]/", '', $sPhone));
        } else {
            return '';
        }

        $sResult = $sMask ?: '+* (***) ***-**-**';

        foreach ($arPhone as $k => $sChar) {
            $iPos = strpos($sResult, '*');
            $sResult = substr_replace($sResult, $sChar, $iPos, 1);
        }

        return $sResult;
    }

    public static function getUserIp()
    {
        return $_SERVER['REMOTE_ADDR'] ?: null;
    }
	
	public static function removeAutoProvide($ids)
	{
		\CAgent::RemoveAgent("\Eva\Administration\Tables\PaymentsTable::setTimeProvide(".$ids.");", "eva.administration");
		return true;
	}
	
	/*
	* нам нужен айди созданной транзакции для сверки платежей
	* стандартные функции не возвращают его
	*/
	public static function UpdateAccountEva($userID, $sum, $currency, $description = "", $orderID = 0, $notes = "", $paymentId = null)
    {
        global $DB, $APPLICATION, $USER;

        $userID = (int)$userID;
        if ($userID <= 0)
        {
            return False;
        }
        $dbUser = \CUser::GetByID($userID);
        if (!$dbUser->Fetch())
        {
            return False;
        }

        $sum = (float)str_replace(",", ".", $sum);

        $currency = trim($currency);
        if ($currency === '')
        {
            return False;
        }

        $orderID = (int)$orderID;
        $paymentId = (int)$paymentId;
        if (!\CSaleUserAccount::Lock($userID, $currency))
        {
            return False;
        }

        $currentBudget = 0.0000;
        $result = false;

        $dbUserAccount = \CSaleUserAccount::GetList(
                array(),
                array("USER_ID" => $userID, "CURRENCY" => $currency)
            );
        if ($arUserAccount = $dbUserAccount->Fetch())
        {
            $currentBudget = floatval($arUserAccount["CURRENT_BUDGET"]);
            $arFields = array(
                    "CURRENT_BUDGET" => $arUserAccount["CURRENT_BUDGET"] + $sum
                );
			// защита от отрицательного баланса, если создаём бредовые платежи из админки
			if($sum >= 0 || ($sum < 0 && $arUserAccount["CURRENT_BUDGET"] >= $sum))
				$result = \CSaleUserAccount::Update($arUserAccount["ID"], $arFields);

        }
        else
        {
            $currentBudget = floatval($sum);
            $arFields = array(
                    "USER_ID" => $userID,
                    "CURRENT_BUDGET" => $sum,
                    "CURRENCY" => $currency,
                    "LOCKED" => "Y",
                    "=DATE_LOCKED" => $DB->GetNowFunction()
                );
			// защита от отрицательного баланса, если создаём бредовые платежи из админки
			if($sum >= 0)
				$result = \CSaleUserAccount::Add($arFields);

        }
		
		$transactID = 0;
        if ($result)
        {
            if (isset($GLOBALS["SALE_USER_ACCOUNT"]["SALE_USER_ACCOUNT_CACHE_".$userID."_".$currency]))
                unset($GLOBALS["SALE_USER_ACCOUNT"]["SALE_USER_ACCOUNT_CACHE_".$userID."_".$currency]);

            $arFields = array(
                    "USER_ID" => $userID,
                    "TRANSACT_DATE" => date($DB->DateFormatToPHP(\CSite::GetDateFormat("FULL", SITE_ID))),
                    "CURRENT_BUDGET" => $currentBudget,
                    "AMOUNT" => ($sum > 0 ? $sum : -$sum),
                    "CURRENCY" => $currency,
                    "DEBIT" => ($sum > 0 ? "Y" : "N"),
                    "ORDER_ID" => ($orderID > 0 ? $orderID : false),
                    "PAYMENT_ID" => ($paymentId > 0 ? $paymentId : false),
                    "DESCRIPTION" => ((strlen($description) > 0) ? $description : null),
                    "NOTES" => ((strlen($notes) > 0) ? $notes : False),
                    "EMPLOYEE_ID" => ($USER->IsAuthorized() ? $USER->GetID() : false)
                );
            \CTimeZone::Disable();
            $transactID = \CSaleUserTransact::Add($arFields);
            \CTimeZone::Enable();
        }

        \CSaleUserAccount::UnLock($userID, $currency);
		return $transactID;
    }

}
?>