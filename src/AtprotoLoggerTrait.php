<?php
namespace Drupal\atproto;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

trait AtprotoLoggerTrait {
  protected LoggerChannelFactoryInterface $loggerFactory;

  public function setLoggerFactory(LoggerChannelFactoryInterface $loggerFactory): void {
    $this->loggerFactory = $loggerFactory;
  }

  protected function logger(): LoggerChannelInterface {
    return $this->loggerFactory->get('atproto');
  }
}