<?php
/**
 * TORO — v1/modules/ProductReviews/Controllers/ProductReviewsController.php
 */
declare(strict_types=1);

final class ProductReviewsController
{
    private ProductReviewsService   $service;
    private ProductReviewsValidator $validator;

    public function __construct()
    {
        $pdo = \Shared\Core\DatabaseConnection::getInstance()->getConnection();
        $this->service   = new ProductReviewsService(new PdoProductReviewsRepository($pdo));
        $this->validator = new ProductReviewsValidator();
    }

    /** GET /v1/products/{productId}/reviews */
    public function indexByProduct(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $productId = (int)($vars['productId'] ?? 0);
        $filters   = [
            'limit'    => (int)($req->getQuery('limit')    ?? 20),
            'offset'   => (int)($req->getQuery('offset')   ?? 0),
        ];
        if ($req->getQuery('approved') !== null) {
            $filters['approved'] = (bool)$req->getQuery('approved');
        }
        $result = $this->service->getForProduct($productId, $filters);
        return \Shared\Core\Response::json($result);
    }

    /** GET /v1/reviews/{id} */
    public function show(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $review = $this->service->getById((int)($vars['id'] ?? 0));
        if (!$review) {
            return \Shared\Core\Response::json(['error' => 'Review not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $review]);
    }

    /** POST /v1/reviews */
    public function store(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $body   = $req->getParsedBody();
        $errors = $this->validator->validateCreate($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }
        $dto    = CreateReviewDTO::fromArray($body);
        $result = $this->service->create($dto);
        return \Shared\Core\Response::json(['data' => $result], 201);
    }

    /** PATCH /v1/reviews/{id} */
    public function update(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $id   = (int)($vars['id'] ?? 0);
        $body = $req->getParsedBody();

        $errors = $this->validator->validateUpdate($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }

        $result = $this->service->update($id, $body);
        if (!$result) {
            return \Shared\Core\Response::json(['error' => 'Review not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $result]);
    }

    /** PATCH /v1/reviews/{id}/approve */
    public function approve(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $id = (int)($vars['id'] ?? 0);
        if (!$this->service->approve($id)) {
            return \Shared\Core\Response::json(['error' => 'Review not found'], 404);
        }
        return \Shared\Core\Response::json(['message' => 'Approved']);
    }

    /** DELETE /v1/reviews/{id} */
    public function destroy(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        if (!$this->service->delete((int)($vars['id'] ?? 0))) {
            return \Shared\Core\Response::json(['error' => 'Review not found'], 404);
        }
        return \Shared\Core\Response::json(['message' => 'Deleted']);
    }
}
