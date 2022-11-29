<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is provided with Magento in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Copyright © 2022 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 *
 */

declare(strict_types=1);

namespace MultiSafepay\ConnectFrontend\Service;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use MultiSafepay\ConnectCore\Config\Config;
use MultiSafepay\ConnectCore\Logger\Logger;
use MultiSafepay\ConnectCore\Util\JsonHandler;
use MultiSafepay\ConnectCore\Util\OrderUtil;
use MultiSafepay\ConnectFrontend\Service\Notification\DelayExecution;
use MultiSafepay\ConnectFrontend\Service\Notification\ProcessOrderTransaction;
use MultiSafepay\Util\Notification as SdkNotification;

class PostNotification
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var JsonHandler
     */
    private $jsonHandler;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var OrderUtil
     */
    private $orderUtil;

    /**
     * @var DelayExecution
     */
    private $delayExecution;

    /**
     * @var ProcessOrderTransaction
     */
    private $processOrderTransaction;

    /**
     * PostNotification constructor.
     *
     * @param Config $config
     * @param JsonHandler $jsonHandler
     * @param Logger $logger
     * @param OrderUtil $orderUtil
     * @param DelayExecution $delayExecution
     * @param ProcessOrderTransaction $processOrderTransaction
     */
    public function __construct(
        Config $config,
        JsonHandler $jsonHandler,
        Logger $logger,
        OrderUtil $orderUtil,
        DelayExecution $delayExecution,
        ProcessOrderTransaction $processOrderTransaction
    ) {
        $this->config = $config;
        $this->jsonHandler = $jsonHandler;
        $this->logger = $logger;
        $this->orderUtil = $orderUtil;
        $this->delayExecution = $delayExecution;
        $this->processOrderTransaction = $processOrderTransaction;
    }

    /**
     * Execute the POST notification process
     *
     * @param RequestInterface $request
     * @param int $storeId
     * @return array
     * @throws Exception
     */
    public function execute(RequestInterface $request, int $storeId): array
    {
        $requestBody = $request->getContent();
        $authHeader = $request->getHeader('Auth');

        // Validate the POST notification
        try {
            if (!SdkNotification::verifyNotification($requestBody, $authHeader, $this->config->getApiKey($storeId))) {
                $this->logger->logFailedPOSTNotification($request->getHeaders()->toString(), $requestBody);
                return ['success' => false, 'message' => 'Unable to verify POST notification'];
            }
        } catch (Exception $exception) {
            return ['success' => false, 'message' => 'Exception occurred when verifying POST notification'];
        }

        $transaction = $this->jsonHandler->readJSON($requestBody);
        $orderIncrementId = $transaction['order_id'] ?? '';

        $this->delayExecution->execute($transaction['status'] ?? '');

        try {
            /** @var Order $order */
            $order = $this->orderUtil->getOrderByIncrementId($orderIncrementId);
        } catch (NoSuchEntityException $noSuchEntityException) {
            $this->logger->logExceptionForOrder($orderIncrementId, $noSuchEntityException);

            return ['success' => false, 'message' => sprintf('%1$s', $noSuchEntityException->getMessage())];
        }

        return $this->processOrderTransaction->execute($order, $transaction);
    }
}
