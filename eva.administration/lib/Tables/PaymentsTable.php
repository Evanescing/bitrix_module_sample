<?php
namespace Eva\Administration\Tables;
use Bitrix\Main,
	Bitrix\Main\Config,
	Bitrix\Main\Entity,
	Bitrix\Main\Loader;
use Eva\Administration\Scripts;

/**
 * Class PaymentsTable
 *
 * @package Bitrix\Paysystems
 **/
class PaymentsTable extends Main\Entity\DataManager
{
	
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'smpayments';
    }
	
	public static function getUfId()
    {
        return 'SMPAYMENTS';
    }
	
    /**
     * Returns entity map definition.
		ID integer
		UF_DATE_PROVIDE datetime
		UF_FROM_PS integer
			ps_1 Внутренний счет
			ps_2 ... etc.
		UF_FROM_USER integer
		UF_TO_PS integer
			ps_1 Внутренний счет
			ps_2 ... etc.
		UF_TO_USER integer
		...etc.
		* secured by author policy *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
            ),
			'UF_DATE_PROVIDE' => array(
                'data_type' => 'datetime',
            ),
			'UF_FROM_PS' => array(
                'data_type' => 'integer',
				'required' => true
            ),
			'UF_FROM_USER' => array(
                'data_type' => 'integer',
				'required' => true
            ),
			'UF_TO_PS' => array(
                'data_type' => 'integer',
				'required' => true
            ),
			'UF_TO_USER' => array(
                'data_type' => 'integer',
				'required' => true
            ),
        );

    }
	
	public static function providePayment($id_payment, $status, $comment='')
	{
		$curDateTime = new \Bitrix\Main\Type\DateTime();
		$arUpdatePayment = array(
			// some fields...
		);
		if(strlen($comment) > 0)
			$arUpdatePayment['UF_COMMENT'] = $comment;
			
		switch ($status) {
			case "status_2":
				break;
			case "status_3":
				$arUpdatePayment['UF_DATE_PROVIDE'] = $curDateTime;
				break;
			case "status_4":
				break;
		}
		
		$bResult = self::update($id_payment, $arUpdatePayment);
		if($bResult->isSuccess())
		{
			return $id_payment;
		}
		else
		{
			$errors = $bResult->getErrors();
			$comment = '';
			foreach ($errors as $error)
			{
				$comment .= '['.$error->getCode().'] '.$error->getMessage();
			}
			$arNUpdatePayment = array(
				'UF_COMMENT' => $comment
			);
			$bNewResult = self::update($id_payment, $arNUpdatePayment);
		}
		return false;
	}
	
	public static function setTimeProvide($id_payment, $comment='')
	{
		if(self::providePayment($id_payment, $status='status_2', $comment))
		{
			//some code
		}
		//$id_payment
	}
	
	public static function onAfterAdd(Entity\Event $event)
    {
        $result = new Entity\EventResult;
        $data = $event->getParameter("fields");
		$paymentId = $event->getParameter("id");
		
		$id = $paymentId;
        if($id)
        {
			$modeAuto = Scripts::getOption('mode_pay');
			if(($modeAuto == 'Y')//автопроведение
			{
				$currentTime = strtotime($data['UF_TIMER_TIME']);
				$timeStep = Scripts::getOption('timer_payments');
				if(intVal($timeStep) < 60)
					$timeStep = 60;
				$timeDo = ($currentTime+intVal($timeStep));
				$obDateDo = \Bitrix\Main\Type\DateTime::createFromTimestamp($timeDo);
				$DateInsert = $obDateDo->toString(new \Bitrix\Main\Context\Culture(array("FORMAT_DATETIME" => \CSite::GetDateFormat())));
				\CAgent::AddAgent("\Eva\Administration\Tables\PaymentsTable::setTimeProvide(".$id.");", "eva.administration", "N", "", $DateInsert, "Y", $DateInsert);
			}
			else
			{
				$comment = '';
				if(strlen($data['UF_COMMENT']) > 0)
					$comment = $data['UF_COMMENT'];
				self::setTimeProvide($id,$comment);
			}
		}

        return $result;
    }
	
	public static function onBeforeUpdate(Entity\Event $event)
    {
        $result = new Entity\EventResult;
        $data = $event->getParameter("fields");
		
		$id = $event->getParameter("id");
		$paymentId = $id["ID"];

        if (isset($paymentId))
        {
			//some code
        }

        return $result;
    }
	
	/*
	* создание платежа при сохранении заказа, если заказ - пополнение счета
	*/
	public static function refillAccount(\Bitrix\Main\Event $event)
	{
		$order = $event->getParameter("ENTITY");
		$oldValues = $event->getParameter("VALUES");
		$arOrderVals = $order->getFields()->getValues();

		if($arOrderVals['STATUS_ID'] == 'N' && !$oldValues['STATUS_ID'])
		{
			$addPayment = false;
			$orderId = $arOrderVals['ID'];
			$psID = intVal($arOrderVals['PAY_SYSTEM_ID']);
			$userId = intVal($arOrderVals['USER_ID']);
			$price = floatVal($arOrderVals['PRICE']);
			$basketOrder = $order->getBasket();
			
			if(count($basketOrder->getQuantityList()) == 1)
			{
				foreach ($basketOrder as $basketItem)
				{
					$propertyItem = '';
					if ($basketPropertyCollection = $basketItem->getPropertyCollection())
					{
						foreach($basketPropertyCollection as $basketPropertyItem)
						{
							$propertyItem = $basketPropertyItem->getField('CODE');
						}
					}
					if($propertyItem == 'SUM_OF_CHARGE')
						$addPayment = true;
				}
				
				if($addPayment)
				{										
					$psCode = Scripts::getOption('paysystemid_'.$psID);
					$curDateTime = new \Bitrix\Main\Type\DateTime();
					$arToPayment = array(
						'UF_FROM_PS' => Scripts::getIdEnumFields(Scripts::getOption('pay_from_prop_id'), 'ps_'.$psID),
						'UF_FROM_USER' => $userId,
						'UF_TO_PS' => Scripts::getIdEnumFields(Scripts::getOption('pay_to_prop_id'), 'ps_1'),
						'UF_TO_USER' => $userId,
						'UF_SUM' => $price,
						'UF_TIMER_TIME' => $curDateTime,
						'UF_ORDER_ID' => $orderId,
					);
					$bResult = self::add($arToPayment);					
					$ID = $bResult->getId();
					if($ID > 0)
					{
						//Уведомление админу
						\CAdminNotify::Add(array(
							'MESSAGE' => 'Новая заявка на пополнение счета <a href="/bitrix/admin/payments_admin.php?PAGEN_1=1&SIZEN_1=20&lang=ru&set_filter=Y&adm_filter_applied=0&find_id='.$ID.'">модерировать</a>.',
							'TAG' => 'schet_'.$ID,
							'MODULE_ID' => 'mymodule',
							'ENABLE_CLOSE' => 'Y',
						));
					}	

				}
				
			}
		}
	}//refillAccount
	
}// class