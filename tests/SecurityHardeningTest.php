<?php

use PHPUnit\Framework\TestCase;

final class SecurityHardeningTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once EPAY_TEST_ROOT.'/plugins/paypal/inc/PayPalClient.php';
    }

    public function testApplicationCodeDoesNotDisableTlsVerification(): void
    {
        $roots = [
            EPAY_TEST_ROOT.'/includes',
            EPAY_TEST_ROOT.'/plugins',
            EPAY_TEST_ROOT.'/user',
        ];
        $violations = [];

        foreach($roots as $root){
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            foreach($iterator as $file){
                if(!$file->isFile() || strtolower($file->getExtension()) !== 'php'){
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                if(preg_match('/CURLOPT_SSL_VERIFYPEER\s*,\s*(?:false|0)|CURLOPT_SSL_VERIFYHOST\s*,\s*(?:false|0)/i', $source)){
                    $violations[] = str_replace(EPAY_TEST_ROOT.'/', '', $file->getPathname());
                }
            }
        }

        self::assertSame([], $violations, '以下文件仍关闭 TLS 校验：'.implode(', ', $violations));
    }

    public function testPaypalCertificateUrlAllowlist(): void
    {
        self::assertTrue(PayPalClient::isAllowedWebhookCertificateUrl(
            'https://api.paypal.com/v1/notifications/certs/CERT-123_example'
        ));
        self::assertTrue(PayPalClient::isAllowedWebhookCertificateUrl(
            'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-123'
        ));

        foreach([
            'http://api.paypal.com/v1/notifications/certs/CERT-123',
            'https://api.paypal.com.evil.example/v1/notifications/certs/CERT-123',
            'https://127.0.0.1/v1/notifications/certs/CERT-123',
            'https://user@api.paypal.com/v1/notifications/certs/CERT-123',
            'https://api.paypal.com:8443/v1/notifications/certs/CERT-123',
            'https://api.paypal.com/v1/notifications/certs/../secrets',
            'https://api.paypal.com/v1/notifications/certs/CERT-123?redirect=1',
        ] as $url){
            self::assertFalse(PayPalClient::isAllowedWebhookCertificateUrl($url), '应拒绝证书地址：'.$url);
        }
    }

    public function testPaypalWebhookUsesFixedVerificationApiInsteadOfFetchingHeaderUrl(): void
    {
        $plugin = file_get_contents(EPAY_TEST_ROOT.'/plugins/paypal/paypal_plugin.php');
        $client = file_get_contents(EPAY_TEST_ROOT.'/plugins/paypal/inc/PayPalClient.php');

        self::assertStringNotContainsString("get_curl(\$_SERVER['HTTP_PAYPAL_CERT_URL'])", $plugin);
        self::assertStringContainsString('verifyWebhookSignature($headers', $plugin);
        self::assertStringContainsString('/v1/notifications/verify-webhook-signature', $client);
    }

    public function testDatabaseUpgradeRequiresAdminPostAndCsrfToken(): void
    {
        $update = file_get_contents(EPAY_TEST_ROOT.'/install/update.php');
        $common = file_get_contents(EPAY_TEST_ROOT.'/includes/common.php');
        $login = file_get_contents(EPAY_TEST_ROOT.'/admin/login.php');

        self::assertStringContainsString("define('IN_DB_UPGRADE', true)", $update);
        self::assertStringContainsString('$islogin !== 1', $update);
        self::assertStringContainsString("\$_SERVER['REQUEST_METHOD'] !== 'POST'", $update);
        self::assertStringContainsString('hash_equals($sessionToken, $submittedToken)', $update);
        self::assertStringContainsString("!defined('IN_DB_UPGRADE')", $common);
        self::assertStringContainsString("define('IN_ADMIN_LOGIN', true)", $login);
    }
}
