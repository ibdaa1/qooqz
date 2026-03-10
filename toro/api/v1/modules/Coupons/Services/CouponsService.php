<?php
/**
 * TORO — v1/modules/Coupons/Services/CouponsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class CouponsService
{
    public function __construct(private readonly CouponsRepositoryInterface $repo) {}

    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getById(int $id, ?string $lang = null): array
    {
        $coupon = $this->repo->findById($id, $lang);
        if (!$coupon) throw new NotFoundException("الكوبون #{$id} غير موجود");
        return $coupon;
    }

    public function validate(string $code, float $orderAmount = 0): array
    {
        $coupon = $this->repo->findByCode(strtoupper($code));
        if (!$coupon) throw new NotFoundException("الكوبون '{$code}' غير موجود");

        if (!(bool)$coupon['is_active']) {
            throw new ValidationException('الكوبون غير فعال', ['code' => 'الكوبون غير فعال']);
        }

        if ($coupon['max_uses'] !== null && (int)$coupon['uses_count'] >= (int)$coupon['max_uses']) {
            throw new ValidationException('تجاوز عدد الاستخدامات', ['code' => 'استنفد هذا الكوبون']);
        }

        $now = date('Y-m-d H:i:s');
        if ($coupon['starts_at'] && $coupon['starts_at'] > $now) {
            throw new ValidationException('الكوبون لم يبدأ بعد', ['code' => 'الكوبون لم يبدأ بعد']);
        }
        if ($coupon['expires_at'] && $coupon['expires_at'] < $now) {
            throw new ValidationException('الكوبون منتهي الصلاحية', ['code' => 'انتهت صلاحية الكوبون']);
        }

        if ($coupon['min_order_amount'] !== null && $orderAmount < (float)$coupon['min_order_amount']) {
            throw new ValidationException(
                'المبلغ أقل من الحد الأدنى',
                ['code' => "الحد الأدنى للطلب {$coupon['min_order_amount']}"]
            );
        }

        return $coupon;
    }

    public function create(CreateCouponDTO $dto, int $actorId): array
    {
        if ($this->repo->findByCode($dto->code)) {
            throw new ValidationException('هذا الكود مستخدم مسبقاً', ['code' => 'يجب أن يكون فريداً']);
        }

        $couponId = $this->repo->create([
            'code'             => $dto->code,
            'type'             => $dto->type,
            'value'            => $dto->value,
            'min_order_amount' => $dto->minOrderAmount,
            'max_uses'         => $dto->maxUses,
            'starts_at'        => $dto->startsAt,
            'expires_at'       => $dto->expiresAt,
            'is_active'        => $dto->isActive,
        ]);

        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($couponId, $langId, $t);
        }

        AuditLogger::log('coupon_created', $actorId, 'coupons', $couponId);

        return array_merge(
            $this->repo->findById($couponId) ?? [],
            ['translations' => $this->repo->getTranslations($couponId)]
        );
    }

    public function update(int $id, UpdateCouponDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الكوبون #{$id} غير موجود");

        if ($dto->code !== null && strtoupper($dto->code) !== $existing['code']) {
            $conflict = $this->repo->findByCode($dto->code);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException('هذا الكود مستخدم مسبقاً', ['code' => 'يجب أن يكون فريداً']);
            }
        }

        $updateData = [];
        foreach (['code', 'type', 'value', 'min_order_amount', 'max_uses', 'starts_at', 'expires_at'] as $field) {
            $prop = lcfirst(str_replace('_', '', ucwords($field, '_')));
            if ($dto->$prop !== null) $updateData[$field] = $dto->$prop;
        }
        if ($dto->isActive !== null) $updateData['is_active'] = (int)$dto->isActive;

        if ($updateData) $this->repo->update($id, $updateData);

        if ($dto->translations !== null) {
            foreach ($dto->translations as $t) {
                $langId = $this->repo->resolveLanguageId($t['lang']);
                if ($langId === null) continue;
                $this->repo->upsertTranslation($id, $langId, $t);
            }
        }

        AuditLogger::log('coupon_updated', $actorId, 'coupons', $id);

        return array_merge(
            $this->repo->findById($id) ?? [],
            ['translations' => $this->repo->getTranslations($id)]
        );
    }

    public function delete(int $id, int $actorId): void
    {
        if (!$this->repo->findById($id)) throw new NotFoundException("الكوبون #{$id} غير موجود");
        $this->repo->delete($id);
        AuditLogger::log('coupon_deleted', $actorId, 'coupons', $id);
    }

    public function getTranslations(int $id): array
    {
        if (!$this->repo->findById($id)) throw new NotFoundException("الكوبون #{$id} غير موجود");
        return $this->repo->getTranslations($id);
    }
}
