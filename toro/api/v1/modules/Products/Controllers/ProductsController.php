<?php
/**
 * TORO — v1/modules/Products/Controllers/ProductsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class ProductsController
{
    private ProductsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new ProductsService(new PdoProductsRepository($pdo));
    }

    // GET /v1/products
    public function index(array $params = []): void
    {
        $filters = [
            'lang'        => $_GET['lang']        ?? null,
            'brand_id'    => isset($_GET['brand_id'])    ? (int)$_GET['brand_id']    : null,
            'category_id' => isset($_GET['category_id']) ? (int)$_GET['category_id'] : null,
            'type'        => $_GET['type']        ?? null,
            'is_active'   => isset($_GET['is_active'])   ? (bool)(int)$_GET['is_active']   : null,
            'is_featured' => isset($_GET['is_featured'])  ? (bool)(int)$_GET['is_featured'] : null,
            'search'      => $_GET['search']      ?? null,
            'limit'       => (int)($_GET['limit']  ?? 50),
            'offset'      => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/products/{id}
    public function show(array $params = []): void
    {
        $product = $this->service->getById((int)($params['id'] ?? 0), $_GET['lang'] ?? null);
        Response::json(['success' => true, 'data' => $product], 200);
    }

    // GET /v1/products/sku/{sku}
    public function showBySku(array $params = []): void
    {
        $product = $this->service->getBySku($params['sku'] ?? '', $_GET['lang'] ?? null);
        Response::json(['success' => true, 'data' => $product], 200);
    }

    // GET /v1/products/{id}/translations
    public function translations(array $params = []): void
    {
        $t = $this->service->getTranslations((int)($params['id'] ?? 0));
        Response::json(['success' => true, 'data' => $t], 200);
    }

    // GET /v1/products/{id}/images
    public function images(array $params = []): void
    {
        $imgs = $this->service->getImages((int)($params['id'] ?? 0));
        Response::json(['success' => true, 'data' => $imgs], 200);
    }

    // POST /v1/products
    public function store(array $params = []): void
    {
        $data = $this->json();
        ProductsValidator::create($data);
        $result = $this->service->create(CreateProductDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PUT /v1/products/{id}
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        ProductsValidator::update($data);
        $result = $this->service->update($id, UpdateProductDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/products/{id}
    public function destroy(array $params = []): void
    {
        $this->service->delete((int)($params['id'] ?? 0), $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف المنتج'], 200);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function json(): array
    {
        return json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    }

    private function authUserId(): int
    {
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) { Response::json(['success' => false, 'message' => 'غير مصرح'], 401); exit; }
        return (int)$id;
    }
}
