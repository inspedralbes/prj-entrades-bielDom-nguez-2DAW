<?php

namespace App\Services\Ticket;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Reserva: QR com a SVG sense dependre del socket-server (mateix contracte que node-qrcode al Node).
 * S’usa si la crida HTTP interna falla (URL, secret, timeout).
 */
class LocalTicketSvgQrService
{
    /**
     * @return non-empty-string|null
     */
    public function svgForPayload(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        try {
            $builder = new Builder(
                writer: new SvgWriter(),
                writerOptions: [
                    SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
                ],
                validateResult: false,
                data: $text,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 256,
                margin: 2,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            );

            $result = $builder->build();
            $svg = $result->getString();

            if ($svg === '') {
                return null;
            }

            return $svg;
        } catch (\Throwable) {
            return null;
        }
    }
}
