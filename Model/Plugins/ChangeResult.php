<?php

namespace Lof\Affiliate\Model\Plugins;

use Magento\Setup\Console\Command\ConfigSetCommand;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChangeResult
{
    /**
     * Before plugin for the ConfigSetCommand.
     *
     * @param ConfigSetCommand $subject
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return array
     */
    public function beforeExecute(
        ConfigSetCommand $subject,
        InputInterface $input,
        OutputInterface $output
    ) {
        // Modify input or output if needed before command execution
        // Example: Change a parameter passed in the command
        // You can manipulate the $input object here

        // Example: Add an argument to change the behavior
        if ($input->hasArgument('some_argument')) {
            $input->setArgument('some_argument', 'new_value');
        }

        // Return the modified input and output
        return [$input, $output];
    }

    /**
     * After plugin for the ConfigSetCommand.
     *
     * @param ConfigSetCommand $subject
     * @param int $result
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function afterExecute(
        ConfigSetCommand $subject,
        $result,
        InputInterface $input,
        OutputInterface $output
    ) {
        // Modify the result after command execution if needed
        // Example: If you want to change the exit code or add additional output

        if ($result === Cli::RETURN_CODE_ERROR) {
            $output->writeln('<error>Something went wrong while executing the command.</error>');
        } else {
            $output->writeln('<info>Command executed successfully.</info>');
        }

        // Modify the result (exit code)
        return $result; // You can change the return code if needed
    }
}
