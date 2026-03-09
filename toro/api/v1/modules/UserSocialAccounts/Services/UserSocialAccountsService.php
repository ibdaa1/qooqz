<?php
/**
 * TORO — v1/modules/UserSocialAccounts/Services/UserSocialAccountsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class UserSocialAccountsService
{
    private const VALID_PROVIDERS = ['google', 'facebook', 'apple'];

    public function __construct(private readonly UserSocialAccountsRepositoryInterface $repo) {}

    public function listByUser(int $userId): array
    {
        return $this->repo->findByUserId($userId);
    }

    public function getById(int $id): array
    {
        $account = $this->repo->findById($id);
        if (!$account) throw new NotFoundException("الحساب الاجتماعي #{$id} غير موجود");
        return $account;
    }

    public function upsert(array $data): array
    {
        $provider    = $data['provider']     ?? '';
        $providerUid = $data['provider_uid'] ?? '';

        if (!in_array($provider, self::VALID_PROVIDERS, true)) {
            throw new ValidationException(['provider' => 'مزود غير مدعوم']);
        }
        if ($providerUid === '') {
            throw new ValidationException(['provider_uid' => 'معرّف المزود مطلوب']);
        }
        if (empty($data['user_id'])) {
            throw new ValidationException(['user_id' => 'معرّف المستخدم مطلوب']);
        }

        $existing = $this->repo->findByProvider($provider, $providerUid);

        if ($existing) {
            $this->repo->update((int)$existing['id'], [
                'token'         => $data['token']         ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at'    => $data['expires_at']    ?? null,
            ]);
            return $this->getById((int)$existing['id']);
        }

        $id = $this->repo->create([
            'user_id'       => (int)$data['user_id'],
            'provider'      => $provider,
            'provider_uid'  => $providerUid,
            'token'         => $data['token']         ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at'    => $data['expires_at']    ?? null,
        ]);

        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        $this->getById($id);
        return $this->repo->delete($id);
    }
}
