<?php

namespace Fazed\TorrentTitleParser;

use Fazed\TorrentTitleParser\Contracts\BlockContract;
use Fazed\TorrentTitleParser\Contracts\BlockFactoryContract;
use Fazed\TorrentTitleParser\Contracts\StringAnalyserContract;
use Fazed\TorrentTitleParser\Exceptions\InvalidBlockDefinition;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Fazed\TorrentTitleParser\Exceptions\BlockDefinitionUnbalanced;
use Fazed\TorrentTitleParser\Exceptions\BlockDefinitionExtractionError;

class StringAnalyser implements StringAnalyserContract
{
    /**
     * The base string which will gets analysed.
     *
     * @var string
     */
    private $sourceString;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @var BlockFactoryContract
     */
    private $blockFactory;

    /**
     * @var null|BlockContract[]
     */
    private $blockCache;

    /**
     * @var null|string
     */
    private $cleanStringCache;

    /**
     * StringAnalyser constructor.
     *
     * @param ConfigRepository $configRepository
     * @param BlockFactoryContract $blockFactory
     */
    public function __construct(
        ConfigRepository $configRepository,
        BlockFactoryContract $blockFactory
    )
    {
        $this->configRepository = $configRepository;
        $this->blockFactory = $blockFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceString()
    {
        return $this->sourceString;
    }

    /**
     * {@inheritdoc}
     */
    public function setSourceString($sourceString)
    {
        $this->sourceString = trim($sourceString);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCleanString($fresh = false)
    {
        if ($fresh || ( ! $fresh && null === $this->cleanStringCache)) {
            return $this->deblockSourceString();
        }

        return $this->cleanStringCache;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlocks($fresh = false)
    {
        if ($fresh || ( ! $fresh && null === $this->blockCache)) {
            return $this->extractBlocks();
        }

        return $this->blockCache;
    }

    /**
     * {@inheritdoc}
     */
    public function getDistinctBlocks()
    {
        return array_unique($this->getBlocks());
    }

    /**
     * Analyse string and extract block data.
     *
     * @return BlockContract[]
     * @throws BlockDefinitionExtractionError
     */
    protected function extractBlocks()
    {
        $registeredBlockDefinitions = (array) $this->configRepository->get(
            'torrent-title-parser.block_definitions', []
        );

        foreach ($registeredBlockDefinitions as $blockDefinition) {
            foreach ($this->extractBlockDefinition($blockDefinition) as $block) {
                $blockStack[] = $block;
            }
        }

        return $this->blockCache = $blockStack ?? [];
    }

    /**
     * Check whether the string contains
     * the given block definition.
     *
     * @param  string[] $blockDefinition
     * @return bool
     * @throws InvalidBlockDefinition
     * @throws BlockDefinitionUnbalanced
     */
    protected function sourceContainsBlockDefinition($blockDefinition)
    {
        $this->validateBlockDefinitionDelimiters($blockDefinition);

        $blockStartDefinitionCount = substr_count($this->sourceString, $blockDefinition[0]);
        $blockCloseDefinitionCount = substr_count($this->sourceString, $blockDefinition[1]);

        if ($blockStartDefinitionCount !== $blockCloseDefinitionCount) {
            throw new BlockDefinitionUnbalanced($blockDefinition);
        }

        return $blockStartDefinitionCount + $blockCloseDefinitionCount >= 2;
    }

    /**
     * Extract the data of the block definition.
     *
     * @param  string[] $blockDefinition
     * @return BlockContract[]
     * @throws BlockDefinitionExtractionError
     */
    protected function extractBlockDefinition($blockDefinition)
    {
        try {
            $this->validateBlockDefinitionDelimiters($blockDefinition);

            if ( ! $this->sourceContainsBlockDefinition($blockDefinition)) {
                return [];
            }

            $blockDefinitionStart = $this->prepareDefinitionDelimiter($blockDefinition[0]);
            $blockDefinitionEnd = $this->prepareDefinitionDelimiter($blockDefinition[1]);
        } catch (InvalidBlockDefinition $e) {
            return [];
        } catch (BlockDefinitionUnbalanced $e) {
            return [];
        }

        $hasMatches = preg_match_all(
            "/\\{$blockDefinitionStart}(.+?)\\{$blockDefinitionEnd}/sui",
            $this->sourceString,
            $blockData,
            PREG_SET_ORDER
        );

        if (false === $hasMatches) {
            throw new BlockDefinitionExtractionError($blockDefinition);
        }

        return array_map(function ($set) use ($blockDefinition) {
            return $this->blockFactory->make($set[1], $blockDefinition);
        }, $blockData);
    }

    /**
     * Strip the blocks from the source string.
     *
     * @return string
     * @throws BlockDefinitionExtractionError
     */
    protected function deblockSourceString()
    {
        $string = $this->sourceString;
        $blocks = $this->getBlocks();

        foreach ($blocks as $block) {
            $blockData =  $block->getRawBlock();
            $blockStart = \strpos($string, $block->getRawBlock());
            $blockEnd = $blockStart + \strlen($block->getRawBlock());

            if ($blockStart > 0 && ctype_space($string[$blockStart - 1])) {
                $blockData = ' ' . $blockData;
            }

            if ($blockEnd < \strlen($string) && ctype_space($string[$blockEnd])) {
                $blockData .= ' ';
            }

            $string = str_replace($blockData, '', $string);
        }

        return $this->cleanStringCache = trim($string);
    }

    /**
     * Prepare regex parts for the given definition delimiters.
     *
     * @param  string $delimiter
     * @return string
     * @throws InvalidBlockDefinition
     */
    protected function prepareDefinitionDelimiter($delimiter)
    {
        if (($delimiterLength = \strlen($delimiter)) === 1) {
            return $delimiter;
        }

        $this->validateBlockDefinitionDelimiter($delimiter);

        return "{$delimiter[0]}{{$delimiterLength}}";
    }

    /**
     * Validate the given block definition delimiters.
     *
     * @param  string[] $definition
     * @return void
     * @throws InvalidBlockDefinition
     */
    protected function validateBlockDefinitionDelimiters($definition)
    {
        if (\count($definition) !== 2) {
            throw new InvalidBlockDefinition($definition);
        }

        foreach ($definition as $delimiter) {
            $this->validateBlockDefinitionDelimiter($delimiter);
        }
    }

    /**
     * Validate a single block definition delimiter.
     *
     * @param  string $delimiter
     * @return void
     * @throws InvalidBlockDefinition
     */
    protected function validateBlockDefinitionDelimiter($delimiter)
    {
        if (($delimiterLength = \strlen($delimiter)) === 1) {
            return;
        }

        for ($i = 0, $iMax = $delimiterLength; $i < $iMax; $i++) {
            if ($i > 0 && $delimiter[$i] !== $delimiter[$delimiterLength - 1]) {
                throw new InvalidBlockDefinition('Block definition should contain identical characters.');
            }
        }
    }
}
