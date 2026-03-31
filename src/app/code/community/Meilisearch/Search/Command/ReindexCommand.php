<?php

/**
 * Maho Command for Meilisearch reindexing
 *
 * @category  Meilisearch
 * @package   Meilisearch_Search
 * @copyright Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @copyright Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @copyright Copyright (c) 2024 Meilisearch
 * @license   https://opensource.org/licenses/OSL-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'meilisearch:reindex',
    description: 'Reindex Meilisearch search indexes',
)]
class Meilisearch_Search_Command_ReindexCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::OPTIONAL, 'Entity type to reindex (products|categories|pages|all)', 'all')
             ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store ID to reindex (only for products)')
             ->addOption('clear', 'c', InputOption::VALUE_NONE, 'Clear indexes before reindexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $storeId = $input->getOption('store');
        $clear = $input->getOption('clear');

        /** @var Meilisearch_Search_Model_Resource_Engine $engine */
        $engine = Mage::getResourceSingleton('meilisearch_search/engine');

        /** @var Meilisearch_Search_Helper_Config $config */
        $config = Mage::helper('meilisearch_search/config');

        // Check if module is enabled
        if (!$config->isModuleOutputEnabled()) {
            $output->writeln('<error>Meilisearch module is disabled</error>');
            return Command::FAILURE;
        }

        try {
            switch ($type) {
                case 'products':
                    $output->writeln('<info>Reindexing products' . ($storeId ? " for store ID $storeId" : ' for all stores') . '...</info>');
                    $engine->rebuildProducts($storeId);
                    $output->writeln('<info>Products reindexed successfully</info>');
                    break;

                case 'categories':
                    if ($storeId) {
                        $output->writeln('<comment>Store option is ignored for categories</comment>');
                    }
                    $output->writeln('<info>Reindexing categories...</info>');
                    $engine->rebuildCategories();
                    $output->writeln('<info>Categories reindexed successfully</info>');
                    break;

                case 'pages':
                    if ($storeId) {
                        $output->writeln('<comment>Store option is ignored for pages</comment>');
                    }
                    $output->writeln('<info>Reindexing pages...</info>');
                    $engine->rebuildPages();
                    $output->writeln('<info>Pages reindexed successfully</info>');
                    break;

                case 'all':
                    $output->writeln('<info>Reindexing all entities' . ($storeId ? " for store ID $storeId" : ' for all stores') . '...</info>');

                    $output->writeln('<info>Reindexing products...</info>');
                    $engine->rebuildProducts($storeId);

                    if (!$storeId) {
                        $output->writeln('<info>Reindexing categories...</info>');
                        $engine->rebuildCategories();

                        $output->writeln('<info>Reindexing pages...</info>');
                        $engine->rebuildPages();
                    }

                    $output->writeln('<info>All entities reindexed successfully</info>');
                    break;

                default:
                    $output->writeln('<error>Unknown reindex type: ' . $type . '</error>');
                    return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $output->writeln('<error>Error during reindexing: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
