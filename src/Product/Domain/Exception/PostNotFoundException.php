<?php
namespace Affilicious\ProductsPlugin\Product\Domain\Exception;

class PostNotFoundException extends \RuntimeException
{
    /**
     * @param string|int $postId
     */
    public function __construct($postId)
    {
        parent::__construct(sprintf(
            __("The post #%s wasn't found.", 'affiliciousproducts'),
            $postId
        ));
    }
}