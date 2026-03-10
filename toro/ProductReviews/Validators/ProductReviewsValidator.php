<?php
/**
 * TORO — v1/modules/ProductReviews/Validators/ProductReviewsValidator.php
 */
declare(strict_types=1);

final class ProductReviewsValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['product_id']) || (int)$data['product_id'] < 1) {
            $errors[] = 'product_id is required';
        }
        if (empty($data['user_id']) || (int)$data['user_id'] < 1) {
            $errors[] = 'user_id is required';
        }
        $rating = (int)($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            $errors[] = 'rating must be between 1 and 5';
        }
        if (isset($data['title']) && mb_strlen($data['title']) > 200) {
            $errors[] = 'title must not exceed 200 characters';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['rating'])) {
            $rating = (int)$data['rating'];
            if ($rating < 1 || $rating > 5) {
                $errors[] = 'rating must be between 1 and 5';
            }
        }
        if (isset($data['title']) && mb_strlen($data['title']) > 200) {
            $errors[] = 'title must not exceed 200 characters';
        }

        return $errors;
    }
}
