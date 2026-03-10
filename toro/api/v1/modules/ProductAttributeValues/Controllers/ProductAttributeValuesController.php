<?php
/**
 * TORO — v1/modules/ProductAttributeValues/Controllers/ProductAttributeValuesController.php
 */
declare(strict_types=1);

final class ProductAttributeValuesController
{
    private ProductAttributeValuesService $service;

    public function __construct()
    {
        $pdo = \Shared\Core\DatabaseConnection::getInstance()->getConnection();
        $this->service = new ProductAttributeValuesService(
            new PdoProductAttributeValuesRepository($pdo)
        );
    }

    /** GET /v1/products/{productId}/attribute-values */
    public function index(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $productId = (int)($vars['productId'] ?? 0);
        $lang      = $req->getQuery('lang');
        $items     = $this->service->getForProduct($productId, $lang ?: null);
        return \Shared\Core\Response::json(['data' => $items]);
    }

    /** POST /v1/products/{productId}/attribute-values */
    public function attach(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $productId = (int)($vars['productId'] ?? 0);
        $body      = $req->getParsedBody();
        $valueId   = (int)($body['value_id'] ?? 0);

        if (!$productId || !$valueId) {
            return \Shared\Core\Response::json(
                ['error' => 'product_id and value_id are required'], 422
            );
        }

        $result = $this->service->attach($productId, $valueId);
        return \Shared\Core\Response::json(['data' => $result], 201);
    }

    /** DELETE /v1/products/{productId}/attribute-values/{valueId} */
    public function detach(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $productId = (int)($vars['productId'] ?? 0);
        $valueId   = (int)($vars['valueId']   ?? 0);

        if (!$this->service->detach($productId, $valueId)) {
            return \Shared\Core\Response::json(['error' => 'Not found'], 404);
        }
        return \Shared\Core\Response::json(['message' => 'Detached'], 200);
    }

    /** PUT /v1/products/{productId}/attribute-values/sync */
    public function sync(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $productId = (int)($vars['productId'] ?? 0);
        $body      = $req->getParsedBody();
        $valueIds  = $body['value_ids'] ?? [];

        if (!$productId) {
            return \Shared\Core\Response::json(['error' => 'productId required'], 422);
        }

        $this->service->sync($productId, (array)$valueIds);
        return \Shared\Core\Response::json(['message' => 'Synced'], 200);
    }
}
