<?php
/**
 * TORO — v1/modules/Wishlists/Controllers/WishlistsController.php
 */
declare(strict_types=1);

final class WishlistsController
{
    private WishlistsService $service;

    public function __construct()
    {
        $pdo = \Shared\Core\DatabaseConnection::getInstance()->getConnection();
        $this->service = new WishlistsService(new PdoWishlistsRepository($pdo));
    }

    /** GET /v1/users/{userId}/wishlist */
    public function index(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $userId  = (int)($vars['userId'] ?? 0);
        $filters = [
            'limit'  => (int)($req->getQuery('limit')  ?? 20),
            'offset' => (int)($req->getQuery('offset') ?? 0),
            'lang'   => $req->getQuery('lang') ?: null,
        ];
        $result = $this->service->getForUser($userId, $filters);
        return \Shared\Core\Response::json($result);
    }

    /** POST /v1/users/{userId}/wishlist */
    public function add(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $userId    = (int)($vars['userId'] ?? 0);
        $body      = $req->getParsedBody();
        $productId = (int)($body['product_id'] ?? 0);

        if (!$userId || !$productId) {
            return \Shared\Core\Response::json(
                ['error' => 'userId and product_id are required'], 422
            );
        }

        $added = $this->service->add($userId, $productId);
        return \Shared\Core\Response::json(
            ['in_wishlist' => true, 'added' => $added],
            $added ? 201 : 200
        );
    }

    /** DELETE /v1/users/{userId}/wishlist/{productId} */
    public function remove(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $userId    = (int)($vars['userId']    ?? 0);
        $productId = (int)($vars['productId'] ?? 0);

        if (!$this->service->remove($userId, $productId)) {
            return \Shared\Core\Response::json(['error' => 'Not in wishlist'], 404);
        }
        return \Shared\Core\Response::json(['in_wishlist' => false]);
    }

    /** DELETE /v1/users/{userId}/wishlist */
    public function clear(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $userId  = (int)($vars['userId'] ?? 0);
        $deleted = $this->service->clear($userId);
        return \Shared\Core\Response::json(['deleted' => $deleted]);
    }

    /** POST /v1/users/{userId}/wishlist/toggle */
    public function toggle(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $userId    = (int)($vars['userId'] ?? 0);
        $body      = $req->getParsedBody();
        $productId = (int)($body['product_id'] ?? 0);

        if (!$userId || !$productId) {
            return \Shared\Core\Response::json(
                ['error' => 'userId and product_id are required'], 422
            );
        }

        $result = $this->service->toggle($userId, $productId);
        return \Shared\Core\Response::json($result);
    }
}
