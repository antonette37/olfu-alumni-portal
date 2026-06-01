<?php
/**
 * Dummy stubs to satisfy IDE/linting when PHPMailer is used without fully installed dependencies.
 */

namespace Psr\Log {
    if (!interface_exists('Psr\Log\LoggerInterface')) {
        interface LoggerInterface
        {
            public function emergency($message, array $context = []);
            public function alert($message, array $context = []);
            public function critical($message, array $context = []);
            public function error($message, array $context = []);
            public function warning($message, array $context = []);
            public function notice($message, array $context = []);
            public function info($message, array $context = []);
            public function debug($message, array $context = []);
            public function log($level, $message, array $context = []);
        }
    }
}

namespace League\OAuth2\Client\Provider {
    if (!class_exists('League\OAuth2\Client\Provider\AbstractProvider')) {
        abstract class AbstractProvider
        {
            public function getAuthorizationUrl(array $options = [])
            {
                return '';
            }
            public function getState()
            {
                return '';
            }
            public function getAccessToken($grant, array $options = [])
            {
                return new \League\OAuth2\Client\Token\AccessToken();
            }
        }
    }
    if (!class_exists('League\OAuth2\Client\Provider\Google')) {
        class Google extends AbstractProvider
        {
        }
    }
}

namespace Hayageek\OAuth2\Client\Provider {
    if (!class_exists('Hayageek\OAuth2\Client\Provider\Yahoo')) {
        class Yahoo extends \League\OAuth2\Client\Provider\AbstractProvider
        {
        }
    }
}

namespace Stevenmaguire\OAuth2\Client\Provider {
    if (!class_exists('Stevenmaguire\OAuth2\Client\Provider\Microsoft')) {
        class Microsoft extends \League\OAuth2\Client\Provider\AbstractProvider
        {
        }
    }
}

namespace Greew\OAuth2\Client\Provider {
    if (!class_exists('Greew\OAuth2\Client\Provider\Azure')) {
        class Azure extends \League\OAuth2\Client\Provider\AbstractProvider
        {
        }
    }
}

namespace League\OAuth2\Client\Token {
    if (!class_exists('League\OAuth2\Client\Token\AccessToken')) {
        class AccessToken
        {
            public function getRefreshToken()
            {
                return '';
            }
            public function hasExpired()
            {
                return false;
            }
            public function __toString()
            {
                return '';
            }
        }
    }
}

namespace League\OAuth2\Client\Grant {
    if (!class_exists('League\OAuth2\Client\Grant\RefreshToken')) {
        class RefreshToken
        {
        }
    }
}
