<?php

declare(strict_types=1);

/**
 * API for Billing
 *
 * @link      https://github.com/hiqdev/billing-hiapi
 * @package   billing-hiapi
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017-2020, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\billing\hiapi\statement\Search;

use Doctrine\Common\Collections\ArrayCollection;
use hiapi\Core\Auth\AuthRule;
use hiqdev\php\billing\statement\Statement;
use hiqdev\php\billing\statement\StatementBill;
use hiqdev\php\billing\statement\StatementRepositoryInterface;

class Action
{
    private StatementRepositoryInterface $repo;

    public function __construct(StatementRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function __invoke(Command $command): ArrayCollection
    {
        $spec = $this->prepareSpecification($command);
        /** @var Statement[] $res */
        $res = $this->repo->findAll($spec);

        return new ArrayCollection($res);
    }

    private function prepareSpecification(Command $command)
    {
        $spec = $command->getSpecification();
        $spec = AuthRule::currentUser()->applyToSpecification($spec);
        if (empty($spec->orderBy)) {
            $spec->orderBy(['time' => SORT_DESC]);
        }

        return $spec;
    }
}
