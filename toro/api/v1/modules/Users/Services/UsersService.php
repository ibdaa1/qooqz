<?php
/**
 * TORO — v1/modules/Users/Services/UsersService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class UsersService
{
    public function __construct(private readonly UsersRepositoryInterface $repo) {}

    // ── List ───────────────────────────────────────────────────
    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ── Get one ────────────────────────────────────────────────
    public function getById(int $id): array
    {
        $user = $this->repo->findById($id);
        if (!$user) throw new NotFoundException("المستخدم #{$id} غير موجود");
        return $user;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): array
    {
        UsersValidator::create($data);

        $email = trim($data['email']);
        if ($this->repo->findByEmail($email)) {
            throw new ValidationException(['email' => 'البريد الإلكتروني مستخدم مسبقاً']);
        }

        $passwordHash = null;
        if (!empty($data['password'])) {
            $passwordHash = password_hash(trim($data['password']), PASSWORD_BCRYPT);
        }

        $id = $this->repo->create([
            'role_id'       => (int)($data['role_id'] ?? 4),
            'first_name'    => trim($data['first_name']),
            'last_name'     => trim($data['last_name']),
            'email'         => $email,
            'phone'         => $data['phone']       ?? null,
            'password_hash' => $passwordHash,
            'language_id'   => isset($data['language_id']) ? (int)$data['language_id'] : null,
            'is_active'     => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        return $this->getById($id);
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): array
    {
        $this->getById($id);
        UsersValidator::update($data);

        $payload = [];

        if (array_key_exists('first_name', $data))  $payload['first_name']  = trim($data['first_name']);
        if (array_key_exists('last_name',  $data))  $payload['last_name']   = trim($data['last_name']);
        if (array_key_exists('phone',      $data))  $payload['phone']       = $data['phone'];
        if (array_key_exists('language_id',$data))  $payload['language_id'] = isset($data['language_id']) ? (int)$data['language_id'] : null;
        if (array_key_exists('is_active',  $data))  $payload['is_active']   = (int)(bool)$data['is_active'];
        if (array_key_exists('role_id',    $data))  $payload['role_id']     = (int)$data['role_id'];

        if (array_key_exists('email', $data)) {
            $email = trim($data['email']);
            $existing = $this->repo->findByEmail($email);
            if ($existing && (int)$existing['id'] !== $id) {
                throw new ValidationException(['email' => 'البريد الإلكتروني مستخدم مسبقاً']);
            }
            $payload['email'] = $email;
        }

        if (!empty($data['password'])) {
            $payload['password_hash'] = password_hash(trim($data['password']), PASSWORD_BCRYPT);
        }

        $this->repo->update($id, $payload);
        return $this->getById($id);
    }

    // ── Soft-delete / Restore ──────────────────────────────────
    public function delete(int $id): bool
    {
        $this->getById($id);
        return $this->repo->softDelete($id);
    }

    public function restore(int $id): bool
    {
        $user = $this->repo->findById($id);
        if (!$user) throw new NotFoundException("المستخدم #{$id} غير موجود");
        return $this->repo->restore($id);
    }
}
