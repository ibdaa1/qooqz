<?php
/**
 * TORO — v1/modules/ProductVariants/Controllers/ProductVariantsController.php
 */
declare(strict_types=1);

final class ProductVariantsController
{
    private ProductVariantsService   $service;
    private ProductVariantsValidator $validator;

    public function __construct()
    {
        $pdo = \Shared\Core\DatabaseConnection::getInstance()->getConnection();
        $this->service   = new ProductVariantsService(new PdoProductVariantsRepository($pdo));
        $this->validator = new ProductVariantsValidator();
    }

    /** GET /v1/products/{productId}/variants */
    public function indexByProduct(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $productId = (int)($vars['productId'] ?? 0);
        $items     = $this->service->getForProduct($productId);
        return \Shared\Core\Response::json(['data' => $items]);
    }

    /** GET /v1/variants/{id} */
    public function show(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $variant = $this->service->getById((int)($vars['id'] ?? 0));
        if (!$variant) {
            return \Shared\Core\Response::json(['error' => 'Variant not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $variant]);
    }

    /** POST /v1/variants */
    public function store(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $body   = $req->getParsedBody();
        $errors = $this->validator->validateCreate($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }
        $dto    = CreateVariantDTO::fromArray($body);
        $result = $this->service->create($dto);
        return \Shared\Core\Response::json(['data' => $result], 201);
    }

    /** PATCH /v1/variants/{id} */
    public function update(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $id   = (int)($vars['id'] ?? 0);
        $body = $req->getParsedBody();

        $errors = $this->validator->validateUpdate($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }

        $dto    = UpdateVariantDTO::fromArray($body);
        $result = $this->service->update($id, $dto);
        if (!$result) {
            return \Shared\Core\Response::json(['error' => 'Variant not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $result]);
    }

    /** DELETE /v1/variants/{id} */
    public function destroy(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        if (!$this->service->delete((int)($vars['id'] ?? 0))) {
            return \Shared\Core\Response::json(['error' => 'Variant not found'], 404);
        }
        return \Shared\Core\Response::json(['message' => 'Deleted'], 200);
    }
}
