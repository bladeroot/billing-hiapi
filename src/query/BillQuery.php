<?php

namespace hiqdev\billing\hiapi\query;

use hiqdev\billing\hiapi\models\Bill;

class BillQuery extends \hiapi\query\Query
{
    protected $modelClass = Bill::class;

    public function initSelect()
    {
        return $this->selectByFields($this->getFields())
            ->from('zbill           zb')
            ->leftJoin('zref        bt', 'bt.obj_id = zb.type_id')
            ->leftJoin('purse       zp', 'zp.obj_id = zb.purse_id')
            ->leftJoin('zclient     zc', 'zc.obj_id = zp.client_id')
            ->leftJoin('zclient     cr', 'cr.obj_id = zc.seller_id')
            ->leftJoin('zref        cu', 'cu.obj_id = zp.currency_id')
            ->leftJoin('obj         zo', 'zo.obj_id = zb.object_id')
            ->leftJoin('zref        oc', 'oc.obj_id = zo.class_id')
        ;
    }

    /**
     * @return mixed
     */
    protected function attributesMap()
    {
        return [
            'id' => 'zb.obj_id',
            'time' => 'zb.time',
            'quantity' => [
                'quantity' => 'zb.quantity',
            ],
            'sum' => [
                'currency' => 'cu.name',
                'amount' => 'zb.sum',
            ],
            'type' => [
                'name' => 'bt.name',
            ],
            'customer' => [
                'id' => 'zc.obj_id',
                'login' => 'zc.login',
                'seller' => [
                    'id' => 'cr.obj_id',
                    'login' => 'cr.login',
                ],
            ],
            'target' => [
                'id' => 'zb.object_id',
                'type' => 'oc.name',
            ],
        ];
    }
}