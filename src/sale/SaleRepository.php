<?php
/**
 * API for Billing
 *
 * @link      https://github.com/hiqdev/billing-hiapi
 * @package   billing-hiapi
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2018, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\billing\hiapi\sale;

use hiqdev\php\billing\action\ActionInterface;
use hiqdev\php\billing\customer\CustomerInterface;
use hiqdev\php\billing\order\OrderInterface;
use hiqdev\php\billing\plan\PlanInterface;
use hiqdev\php\billing\sale\Sale;
use hiqdev\php\billing\sale\SaleInterface;
use hiqdev\php\billing\sale\SaleRepositoryInterface;
use hiqdev\yii\DataMapper\expressions\CallExpression;
use hiqdev\yii\DataMapper\expressions\HstoreExpression;
use hiqdev\yii\DataMapper\models\relations\Bucket;
use hiqdev\yii\DataMapper\query\Specification;
use hiqdev\yii\DataMapper\repositories\BaseRepository;
use yii\db\Query;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    /** {@inheritdoc} */
    public $queryClass = SaleQuery::class;

    public function findId(SaleInterface $sale)
    {
        if ($sale->hasId()) {
            return $sale->getId();
        }
        $hstore = new HstoreExpression(array_filter([
            'buyer'     => $sale->getCustomer()->getLogin(),
            'buyer_id'  => $sale->getCustomer()->getId(),
            'object_id' => $sale->getTarget()->getId(),
            'tariff_id' => $sale->getPlan()->getId(),
        ], static function ($value): bool {
            return $value !== null;
        }, ARRAY_FILTER_USE_BOTH));
        $call = new CallExpression('sale_id', [$hstore]);
        $command = (new Query())->select($call);

        return $command->scalar($this->db);
    }

    /**
     * @param OrderInterface $order
     * @return Sale[]|SaleInterface[]
     */
    public function findByOrder(OrderInterface $order)
    {
        return array_map([$this, 'findByAction'], $order->getActions());
    }

    /**
     * Used to find a sale by action target.
     *
     * @param ActionInterface $action
     * @return SaleInterface|false
     */
    public function findByAction(ActionInterface $action)
    {
        $type = $action->getTarget()->getType();
        if ($type === null) {
            // When action target type is not set, then action can be applied to any target.
            // It means we can not find exact sale, so return null.
            // Used at lest for:
            //  - temporary actions, when in-memory action is matched against an in-memory plan.
            return false;
        }

        if ($type === 'certificate') {
            $target_id = new CallExpression('class_id', ['certificate']);
        } elseif ($type === 'domain' || $type === 'feature') {
            $target_id = new CallExpression('class_id', ['zone']);
        } elseif ($type === 'server' || $type === 'device') {
            $target_id = $action->getTarget()->getId();
        } elseif ($type === 'serverConfig') {
            $target_id = $action->getTarget()->getId();
        } elseif ($type === 'part') {
            // Crutch. Actions for specific parts are currently used at least in
            // - DeviceMonthlyEstimateMux
            //
            // For regular billing, all actions are created for the whole server
            // but have type monthly,hardware.
            return false;
        } else {
            throw new \Exception('not implemented for: ' . $type);
        }

        $spec = $this->createSpecification()
            /// XXX how to pass if we want with prices into joinPlans?
            ->with('plans')
            ->where($this->buildTargetCond($target_id, $action->getCustomer()));

        return $this->findOne($spec);
    }

    protected function buildTargetCond($target_id, CustomerInterface $buyer)
    {
        $condition = ['target-id' => $target_id];

        $client_id = $buyer->getId();
        if ($client_id) {
            $condition['customer-id'] = $client_id;
            $condition['seller-id_ne'] = $client_id;
        } else {
            $condition['customer-id'] = $condition['seller-id'] = $buyer->getSeller()->getId();
        }

        return $condition;
    }

    protected function joinPlans(&$rows)
    {
        $bucket = Bucket::fromRows($rows, 'plan-id');
        $spec = $this->createSpecification()
            ->with('prices')
            ->where(['id' => $bucket->getKeys()]);
        $raw_plans = $this->getRepository(PlanInterface::class)->queryAll($spec);
        $bucket->fill($raw_plans, 'id');
        $bucket->pourOneToOne($rows, 'plan');
    }

    /**
     * @param SaleInterface $sale
     */
    public function save(SaleInterface $sale)
    {
        $hstore = new HstoreExpression([
            'object_id'     => $sale->getTarget()->getId(),
            'contact_id'    => $sale->getCustomer()->getId(),
            'tariff_id'     => $sale->getPlan() ? $sale->getPlan()->getId() : null,
            'time'          => $sale->getTime()->format('c'),
        ]);
        $call = new CallExpression('sale_object', [$hstore]);
        $command = (new Query())->select($call);
        $sale->setId($command->scalar($this->db));
    }
}
