<?php
/**
 * TORO — v1/modules/Menus/Controllers/MenusController.php
 */
declare(strict_types=1);

final class MenusController
{
    private MenusService   $service;
    private MenusValidator $validator;

    public function __construct()
    {
        $pdo = \Shared\Core\DatabaseConnection::getInstance()->getConnection();
        $this->service   = new MenusService(
            new PdoMenusRepository($pdo),
            new PdoMenuItemsRepository($pdo)
        );
        $this->validator = new MenusValidator();
    }

    // ── Menus ──────────────────────────────────────────────────

    /** GET /v1/menus */
    public function index(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        return \Shared\Core\Response::json(['data' => $this->service->listMenus()]);
    }

    /** GET /v1/menus/{id} */
    public function show(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $menu = $this->service->getMenuById((int)($vars['id'] ?? 0));
        if (!$menu) {
            return \Shared\Core\Response::json(['error' => 'Menu not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $menu]);
    }

    /** GET /v1/menus/by-slug/{slug} */
    public function showBySlug(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $lang = $req->getQuery('lang');
        $menu = $this->service->getMenuBySlug($vars['slug'] ?? '', $lang ?: null);
        if (!$menu) {
            return \Shared\Core\Response::json(['error' => 'Menu not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $menu]);
    }

    /** POST /v1/menus */
    public function store(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $body   = $req->getParsedBody();
        $errors = $this->validator->validateCreateMenu($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }
        $dto    = CreateMenuDTO::fromArray($body);
        $result = $this->service->createMenu($dto);
        return \Shared\Core\Response::json(['data' => $result], 201);
    }

    /** PATCH /v1/menus/{id} */
    public function update(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $id     = (int)($vars['id'] ?? 0);
        $body   = $req->getParsedBody();
        $result = $this->service->updateMenu($id, $body);
        if (!$result) {
            return \Shared\Core\Response::json(['error' => 'Menu not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $result]);
    }

    /** DELETE /v1/menus/{id} */
    public function destroy(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        if (!$this->service->deleteMenu((int)($vars['id'] ?? 0))) {
            return \Shared\Core\Response::json(['error' => 'Menu not found'], 404);
        }
        return \Shared\Core\Response::json(['message' => 'Deleted']);
    }

    // ── Menu Items ─────────────────────────────────────────────

    /** GET /v1/menus/{menuId}/items */
    public function items(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $menuId = (int)($vars['menuId'] ?? 0);
        $lang   = $req->getQuery('lang');
        $tree   = $req->getQuery('tree');

        if ($tree) {
            $data = $this->service->getTreeForMenu($menuId, $lang ?: null);
        } else {
            $data = $this->service->getItemsForMenu($menuId, $lang ?: null);
        }
        return \Shared\Core\Response::json(['data' => $data]);
    }

    /** GET /v1/menu-items/{id} */
    public function showItem(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $pdo  = \Shared\Core\DatabaseConnection::getInstance()->getConnection();
        $repo = new PdoMenuItemsRepository($pdo);
        $item = $repo->findById((int)($vars['id'] ?? 0));
        if (!$item) {
            return \Shared\Core\Response::json(['error' => 'Item not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $item]);
    }

    /** POST /v1/menu-items */
    public function storeItem(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $body   = $req->getParsedBody();
        $errors = $this->validator->validateCreateItem($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }
        $dto    = CreateMenuItemDTO::fromArray($body);
        $result = $this->service->createItem($dto);
        return \Shared\Core\Response::json(['data' => $result], 201);
    }

    /** PATCH /v1/menu-items/{id} */
    public function updateItem(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $id     = (int)($vars['id'] ?? 0);
        $body   = $req->getParsedBody();
        $errors = $this->validator->validateUpdateItem($body);
        if ($errors) {
            return \Shared\Core\Response::json(['errors' => $errors], 422);
        }
        $result = $this->service->updateItem($id, $body);
        if (!$result) {
            return \Shared\Core\Response::json(['error' => 'Item not found'], 404);
        }
        return \Shared\Core\Response::json(['data' => $result]);
    }

    /** DELETE /v1/menu-items/{id} */
    public function destroyItem(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        if (!$this->service->deleteItem((int)($vars['id'] ?? 0))) {
            return \Shared\Core\Response::json(['error' => 'Item not found'], 404);
        }
        return \Shared\Core\Response::json(['message' => 'Deleted']);
    }

    /** PUT /v1/menus/{menuId}/items/reorder */
    public function reorderItems(array $vars, \Shared\Core\Request $req): \Shared\Core\Response
    {
        $menuId    = (int)($vars['menuId'] ?? 0);
        $body      = $req->getParsedBody();
        $orderedIds = $body['ordered_ids'] ?? [];

        if (!$menuId || empty($orderedIds)) {
            return \Shared\Core\Response::json(['error' => 'menuId and ordered_ids are required'], 422);
        }
        $this->service->reorderItems($menuId, (array)$orderedIds);
        return \Shared\Core\Response::json(['message' => 'Reordered']);
    }
}
