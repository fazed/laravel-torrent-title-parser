<?php

namespace Fazed\Strowel\Contracts;

interface BlockParserResultFactoryContract
{
    /**
     * Create a new ParserResultContract instance.
     *
     * @param  string $source
     * @param  array  $blockData
     * @param  array  $rawBlockData
     * @param  string $cleanSource
     * @return ParserResultContract
     */
    public function make(
        string $source,
        array $blockData,
        array $rawBlockData,
        string $cleanSource
    ): ParserResultContract;
}
