<?php

namespace Surface\Contracts\Framebuffers;

interface FormatSpecification
{
    public function formatSpec(): FormatSpec;
    public function generateFormatSpec(): FormatSpec;
    public function setFormatSpec(FormatSpec $format_spec): void;
}