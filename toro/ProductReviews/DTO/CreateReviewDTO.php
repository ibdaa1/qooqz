<?php
/**
 * TORO — v1/modules/ProductReviews/DTO/CreateReviewDTO.php
 */
declare(strict_types=1);

final class CreateReviewDTO
{
    public function __construct(
        public readonly int     $productId,
        public readonly int     $userId,
        public readonly int     $rating,
        public readonly ?string $title = null,
        public readonly ?string $body  = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            productId: (int)($d['product_id'] ?? 0),
            userId:    (int)($d['user_id']    ?? 0),
            rating:    (int)($d['rating']     ?? 0),
            title:     isset($d['title']) && $d['title'] !== '' ? trim($d['title']) : null,
            body:      isset($d['body'])  && $d['body']  !== '' ? trim($d['body'])  : null,
        );
    }
}
