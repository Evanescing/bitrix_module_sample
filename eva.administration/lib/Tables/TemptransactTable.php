<?php
namespace Eva\Administration\Tables;
use Bitrix\Main,
	Bitrix\Main\Config,
	Bitrix\Main\Entity;
use Eva\Administration\Scripts;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class TemptransactTable
 *
 * @package Bitrix\Paysystems
 **/
class TemptransactTable extends Main\Entity\DataManager
{
	
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'temporary_transactions';
    }
	
	public static function getUfId()
    {
        return 'TEMPTRANSACTIONS';
    }
	
    /**
     * Returns entity map definition.
		ID integer
		UF_TIMENOW datetime
		UF_PAYMENT_ID integer
		UF_TRANSACTID integer
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
			'UF_TIMENOW' => array(
                'data_type' => 'datetime',
				'default_value' => new \Bitrix\Main\Type\DateTime(),
            ),
			'UF_PAYMENT_ID' => array(
                'data_type' => 'integer',
				'required' => true
            ),
			'UF_TRANSACTID' => array(
                'data_type' => 'integer'
            ),
			'UF_SUM' => array(
                'data_type' => 'float',
				'required' => true
            ),
			'UF_USERID' => array(
                'data_type' => 'integer',
				'required' => true
            )
        );

    }
	
	/*
	* получаем все записи временных сумм для пользователя
	*/
	public static function getTemporaryBlockedSum($userID)
	{
		$sum = $sumAll = 0;
		$itemsResult = self::getList(array(
			'select' => array('*'), 
			'filter' => array(
				'UF_USERID' => $userID
			)
		));
		while($item = $itemsResult->fetch())
		{
			$sumAll = $sum + $item['UF_SUM'];
		}
		
		return $sumAll;
	}
	
}// class
