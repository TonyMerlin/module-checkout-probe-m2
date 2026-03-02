<?php
declare(strict_types=1);

namespace Merlin\CheckoutProbe\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class EventWriter
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @param array<string,mixed> $row
     */
    public function write(array $row): void
    {
        try {
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName('merlin_checkoutprobe_event');

            if (isset($row['context_json']) && is_array($row['context_json'])) {
                $row['context_json'] = $this->json->serialize($row['context_json']);
            }

            $conn->insert($table, $row);
        } catch (\Throwable $e) {
            // Never break checkout because of probe logging
            $this->logger->error('[CheckoutProbe] DB write failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
