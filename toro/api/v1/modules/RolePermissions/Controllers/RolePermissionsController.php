<?php
/**
 * TORO — v1/modules/RolePermissions/Controllers/RolePermissionsController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class RolePermissionsController
{
    private RolePermissionsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        // نحتاج إلى PdoRolesRepository و PdoPermissionsRepository
        // نفترض أنهما معرفان مسبقاً، أو يمكننا استخدام new مباشرة إذا كانت الملفات محملة
        require_once __DIR__ . '/../Roles/Repositories/PdoRolesRepository.php';
        require_once __DIR__ . '/../Permissions/Repositories/PdoPermissionsRepository.php';

        $this->service = new RolePermissionsService(
            new PdoRolePermissionsRepository($pdo),
            new PdoRolesRepository($pdo),
            new PdoPermissionsRepository($pdo),
        );
    }

    // ── GET /v1/role-permissions/role/{roleId} ─────────────────
    public function getPermissionsByRole(array $params = []): void
    {
        $roleId = (int)($params['roleId'] ?? 0);
        $perms = $this->service->getPermissionsByRole($roleId);
        Response::json(['success' => true, 'data' => $perms], 200);
    }

    // ── GET /v1/role-permissions/permission/{permissionId} ─────
    public function getRolesByPermission(array $params = []): void
    {
        $permId = (int)($params['permissionId'] ?? 0);
        $roles = $this->service->getRolesByPermission($permId);
        Response::json(['success' => true, 'data' => $roles], 200);
    }

    // ── GET /v1/role-permissions/exists ────────────────────────
    public function exists(array $params = []): void
    {
        $query = [
            'role_id' => $_GET['role_id'] ?? null,
            'permission_id' => $_GET['permission_id'] ?? null,
        ];
        RolePermissionsValidator::exists($query);
        $exists = $this->service->checkExists((int)$query['role_id'], (int)$query['permission_id']);
        Response::json(['success' => true, 'data' => ['exists' => $exists]], 200);
    }

    // ── POST /v1/role-permissions/attach ───────────────────────
    public function attach(array $params = []): void
    {
        $data = $this->json();
        RolePermissionsValidator::attach($data);
        $dto = AttachPermissionsDTO::fromArray($data);
        $result = $this->service->attach($dto, $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── POST /v1/role-permissions/detach ───────────────────────
    public function detach(array $params = []): void
    {
        $data = $this->json();
        RolePermissionsValidator::detach($data);
        $dto = AttachPermissionsDTO::fromArray($data);
        $result = $this->service->detach($dto, $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── POST /v1/role-permissions/sync ─────────────────────────
    public function sync(array $params = []): void
    {
        $data = $this->json();
        RolePermissionsValidator::sync($data);
        $dto = SyncPermissionsDTO::fromArray($data);
        $result = $this->service->sync($dto, $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/role-permissions ────────────────────────────
    public function destroy(array $params = []): void
    {
        $data = $this->json();
        RolePermissionsValidator::destroy($data);
        $this->service->deleteRelation(
            (int)$data['role_id'],
            (int)$data['permission_id'],
            $this->authUserId()
        );
        Response::json(['success' => true, 'message' => 'تم حذف العلاقة'], 200);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }

    private function authUserId(): int
    {
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) {
            Response::json(['success' => false, 'message' => 'غير مصرح'], 401);
            exit;
        }
        return (int)$id;
    }
}