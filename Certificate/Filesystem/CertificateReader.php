<?php

declare(strict_types=1);

namespace AdyenPayment\Certificate\Filesystem;

use AdyenPayment\Certificate\Exception\CouldNotReadCertificate;
use AdyenPayment\Certificate\Model\ApplePayCertificate;

final class CertificateReader implements CertificateReaderInterface
{
    /**
     * @throws CouldNotReadCertificate
     */
    public function __invoke(): ApplePayCertificate
    {
        $certificatePath = CertificateWriter::APPLE_PAY_CERTIFICATE_DIR.'/'.CertificateWriter::APPLE_PAY_CERTIFICATE;
        $fileContent = false;
        if (file_exists($certificatePath)) {
            $fileContent = file_get_contents($certificatePath);
        }

        if (!$fileContent) {
            throw CouldNotReadCertificate::withFilepath($certificatePath);
        }

        return ApplePayCertificate::create($fileContent);
    }
}