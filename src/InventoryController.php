<?php
require_once __DIR__ . "/../crest/crest.php";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('INVENTORY_ENTITY_TYPE_ID', 1130);

class InventoryController
{
    private $cacheExpiry = 300;

    public function processRequest(string $method, ?string $id, int $page = 1): void
    {
        if ($method !== 'GET') {
            $this->sendErrorResponse(405, "Method not allowed");
            return;
        }

        $id ? $this->processResourceRequest($id) : $this->processCollectionRequest($page);
    }

    private function processResourceRequest(string $id): void
    {
        $cacheKey = "inventory_item_{$id}";
        if ($cachedData = $this->getCache($cacheKey)) {
            $this->sendJsonResponse($cachedData);
        }

        $response = CRest::call('crm.item.get', [
            'entityTypeId' => INVENTORY_ENTITY_TYPE_ID,
            'id' => $id,
            'select' => $this->getFields()
        ]);

        $data = $response['result']['item'] ?? [];
        if (empty($data)) {
            $this->sendErrorResponse(404, "Item not found");
        }

        $transformedData = $this->transformItem($data);
        $this->setCache($cacheKey, $transformedData);
        $this->sendJsonResponse($transformedData);
    }

    private function processCollectionRequest(int $page): void
    {
        $limit = 50;
        $start = ($page - 1) * $limit;
        $cacheKey = "inventory_page_{$page}";

        if ($cachedData = $this->getCache($cacheKey)) {
            $this->sendJsonResponse($cachedData);
        }

        $response = CRest::call('crm.item.list', [
            'entityTypeId' => INVENTORY_ENTITY_TYPE_ID,
            'start' => $start,
            'order' => ['id' => 'desc'],
            'select' => $this->getFields()
        ]);

        $data = array_map([$this, 'transformItem'], $response['result']['items'] ?? []);
        $result = ["page" => $page, "total" => $response['total'] ?? count($data), "data" => $data];

        $this->setCache($cacheKey, $result);
        $this->sendJsonResponse($result);
    }

    private function transformItem(array $item): array
    {
        return [
            "id" => $item['id'],
            "reference" => $item['ufCrm48ReferenceNumber'] ?? '',
            "title" => $item['ufCrm48ListingTitle'] ?? '',
            "bedrooms" => $item['ufCrm48Bedrooms'] ?? 0,
            "bathrooms" => $item['ufCrm48Bathrooms'] ?? 0,
            "price" => isset($item['ufCrm48Price']) ? number_format($item['ufCrm48Price'], 2) : '0.00',
            "status" => match ($item['ufCrm48Status'] ?? null) {
                41323 => "Published",
                41324 => "Pocket",
                default => "Unknown",
            },
            "projectStatus" => match ($item['ufCrm48ProjectStatus'] ?? null) {
                "off_plan" => "Off Plan",
                "off_plan_primary" => "Off-Plan Primary",
                "off_plan_secondary" => "Off-Plan Secondary",
                "ready_primary" => "Ready Primary",
                "ready_secondary" => "Ready Secondary",
                "completed" => "Completed",
                default => "",
            },
            "ownerPhone" => $item['ufCrm48OwnerPhone'] ?? '',
            "unitType" => $item['ufCrm48UnitType'] ?? '',
            "locationPf" => $item['ufCrm48LocationPf'] ?? '',
            "locationBayut" => $item['ufCrm48LocationBayut'] ?? '',
            "size" => $item['ufCrm48Size'] ?? 0,
            "agentName" => $item['ufCrm48AgentName'] ?? '',
            "ownerName" => $item['ufCrm48OwnerName'] ?? '',
            "ownerUrl" => $item['ufCrm48OwnerUrl'] ?? '#',
            "images" => array_map(fn($image) => ["url" => $image], $item['ufCrm48PropertyImages'] ?? [])
        ];
    }

    private function getFields(): array
    {
        return [
            'id',
            'ufCrm48ReferenceNumber',
            'ufCrm48ListingTitle',
            'ufCrm48Bedrooms',
            'ufCrm48Bathrooms',
            'ufCrm48Price',
            'ufCrm48Status',
            'ufCrm48ProjectStatus',
            'ufCrm48OwnerPhone',
            'ufCrm48UnitType',
            'ufCrm48LocationPf',
            'ufCrm48LocationBayut',
            'ufCrm48Size',
            'ufCrm48AgentName',
            'ufCrm48OwnerName',
            'ufCrm48PropertyImages',
            'ufCrm48OwnerUrl'
        ];
    }

    private function getCache(string $key)
    {
        $cacheFile = sys_get_temp_dir() . "/bitrix_" . md5($key) . ".cache";
        return (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheExpiry))
            ? json_decode(file_get_contents($cacheFile), true)
            : false;
    }

    private function setCache(string $key, $data): void
    {
        file_put_contents(sys_get_temp_dir() . "/bitrix_" . md5($key) . ".cache", json_encode($data));
    }

    private function sendJsonResponse($data): void
    {
        header("Content-Type: application/json");
        header("Cache-Control: max-age=300, public");
        echo json_encode($data);
        exit;
    }

    private function sendErrorResponse(int $code, string $message): void
    {
        http_response_code($code);
        $this->sendJsonResponse(["error" => $message]);
    }
}
