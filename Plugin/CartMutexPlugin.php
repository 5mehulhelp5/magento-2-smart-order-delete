<?php
namespace Thinkbeat\SmartOrderDelete\Plugin;

use Magento\Quote\Model\CartMutex;
use Magento\Quote\Model\CartLockedException;

class CartMutexPlugin
{
    /**
     * Retry CartMutex execution to bypass the Magento 2.4.7 CartLockedException bug
     * if the cart is temporarily locked due to minor performance hiccups.
     *
     * @param CartMutex $subject
     * @param callable $proceed
     * @param mixed $quoteIds
     * @param callable $closure
     * @param array $args
     * @return mixed
     * @throws CartLockedException
     */
    public function aroundExecute(CartMutex $subject, callable $proceed, $quoteIds, callable $closure, array $args = [])
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $proceed($quoteIds, $closure, $args);
            } catch (CartLockedException $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                // Sleep for 1 second before retrying
                sleep(1);
            }
        }
    }
}
