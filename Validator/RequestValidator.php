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
 * Copyright © 2021 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 *
 */

declare(strict_types=1);

namespace MultiSafepay\ConnectFrontend\Validator;

use MultiSafepay\ConnectCore\Config\Config;
use MultiSafepay\ConnectCore\Logger\Logger;
use MultiSafepay\ConnectCore\Model\SecureToken;

class RequestValidator
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var SecureToken
     */
    private $secureToken;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Config $config,
        Logger $logger,
        SecureToken $secureToken
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->secureToken = $secureToken;
    }

    /**
     * @param array $parameters
     * @return bool
     */
    public function validateSecureToken(array $parameters): bool
    {
        if (!($orderId = $parameters['transactionid'] ?? null)) {
            return false;
        }

        if (!($secureToken = $parameters['secureToken'] ?? null)) {
            $this->logger->logMissingSecureToken($orderId);

            return false;
        }

        if (!$this->secureToken->validate($orderId, $secureToken)) {
            $this->logger->logInvalidSecureToken($orderId);

            return false;
        }

        return true;
    }

    /**
     * @param string $authHeader
     * @param string $requestBody
     * @param int $storeId
     * @return bool
     */
    public function validatePostNotification(string $authHeader, string $requestBody, int $storeId): bool
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $timestampAndHash = explode(':', (string)base64_decode($authHeader));

        if (!isset($timestampAndHash[0], $timestampAndHash[1])) {
            return false;
        }

        $hashToCheck = hash_hmac(
            'sha512',
            $timestampAndHash[0] . ':' . $requestBody,
            $this->config->getApiKey($storeId)
        );

        return $hashToCheck === $timestampAndHash[1];
    }
}
