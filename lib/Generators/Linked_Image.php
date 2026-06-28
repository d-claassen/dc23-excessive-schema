<?php

namespace DC23\ExcessiveSchema\Generators;

use \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

class Linked_Image extends Abstract_Schema_Piece {

    public function is_needed() {
        return $this->context->has_article();
    }
    
    public function generate() {
        $image_pieces = [];
        
        return $image_pieces;
    }
}