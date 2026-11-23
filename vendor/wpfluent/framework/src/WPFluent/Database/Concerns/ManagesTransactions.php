<?php

namespace WPSocialReviews\Framework\Database\Concerns;

use Closure;
use Throwable;
use RuntimeException;
use WPSocialReviews\Framework\Database\DeadlockException;

trait ManagesTransactions
{
    /**
     * Execute a Closure within a transaction.
     *
     * @template TReturn
     * @param  \Closure(static): TReturn  $callback
     * @param  int  $attempts
     * @return TReturn
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {

            $this->beginTransaction();

            try {
                $result = $callback($this);
            } catch (Throwable $e) {
                $this->handleTransactionException($e, $currentAttempt, $attempts);
                continue;
            }

            try {
                $levelBeingCommitted = $this->performCommit();
            } catch (Throwable $e) {
                $this->handleCommitTransactionException($e, $currentAttempt, $attempts);
                continue;
            }

            // The after-commit callbacks run outside the try block, so an
            // exception thrown by one is not mistaken for a failed commit.
            $this->finishCommit($levelBeingCommitted);

            return $result;
        }
    }

    /**
     * Begin transaction or create savepoint.
     */
    public function beginTransaction()
    {
        if ($this->inTransaction()) {

            $this->transactions++;

            if ($this->queryGrammar->supportsSavepoints()) {
                try {
                    $this->createSavepoint();
                } catch (Throwable $e) {
                    $this->transactions--;

                    throw $e;
                }
            }

            if ($this->transactionsManager) {
                $this->transactionsManager->begin(
                    $this->getName(),
                    $this->transactions
                );
            }

            $this->fireConnectionEvent('beganTransaction');

            return;
        }

        // Run any registered pre-transaction callbacks before issuing
        // the BEGIN — useful for setting session vars, acquiring locks,
        // or other per-transaction connection setup.
        foreach ($this->beforeStartingTransaction as $callback) {
            $callback($this);
        }

        $this->transactions++;

        $this->createTransaction();

        if ($this->transactionsManager) {
            $this->transactionsManager->begin(
                $this->getName(),
                $this->transactions
            );
        }

        $this->fireConnectionEvent('beganTransaction');
    }

    /**
     * Create root transaction.
     */
    protected function createTransaction()
    {
        // Defensive: only issue the top-level BEGIN if we just entered
        // the first transaction level. Prevents duplicate BEGIN on
        // mis-call from a subclass or refactor.
        if ($this->transactions === 1) {
            try {
                $this->transactionStatement('START TRANSACTION;');
            } catch (Throwable $e) {
                try {
                    $this->handleBeginTransactionException($e);
                } catch (Throwable $e) {
                    $this->transactions--;

                    throw $e;
                }
            }
        }
    }

    /**
     * Create savepoint.
     */
    protected function createSavepoint()
    {
        if ($this->nestsTransactionsItself()) {
            $this->transactionStatement('START TRANSACTION;');

            return;
        }

        $name = $this->getSavepointName($this->transactions);
        $this->transactionStatement(
            $this->queryGrammar->compileSavepoint($name) . ';'
        );
    }

    /**
     * Commit transaction or release savepoint.
     */
    public function commit()
    {
        if ($this->transactions === 0) {
            return;
        }

        $this->finishCommit($this->performCommit());
    }

    /**
     * Issue the COMMIT or release the savepoint, and lower the level.
     *
     * @return int  The level that was committed.
     */
    protected function performCommit()
    {
        if ($this->transactions === 1) {

            $this->fireConnectionEvent('committing');
            $this->transactionStatement('COMMIT;');

        } elseif ($this->nestsTransactionsItself()) {

            $this->transactionStatement('COMMIT;');

        } elseif ($this->queryGrammar->supportsSavepoints()) {

            $name = $this->getSavepointName($this->transactions);
            $this->transactionStatement(
                $this->queryGrammar->compileSavepointRelease($name) . ';'
            );
        }

        [$levelBeingCommitted, $this->transactions] = [
            $this->transactions,
            max(0, $this->transactions - 1),
        ];

        return $levelBeingCommitted;
    }

    /**
     * Tell the transactions manager about a commit and fire the event.
     *
     * @param  int  $levelBeingCommitted
     * @return void
     */
    protected function finishCommit($levelBeingCommitted)
    {
        if ($this->transactionsManager) {
            $this->transactionsManager->commit(
                $this->getName(),
                $levelBeingCommitted,
                $this->transactions
            );
        }

        $this->fireConnectionEvent('committed');
    }

    /**
     * Rollback transaction.
     */
    public function rollBack($toLevel = null)
    {
        $toLevel = is_null($toLevel)
            ? $this->transactions - 1
            : $toLevel;

        if ($toLevel < 0 || $toLevel >= $this->transactions) {
            return;
        }

        // The level goes down even when the statement fails, so the count
        // never drifts from what the caller left behind.
        $failure = null;

        try {
            $this->performRollBack($toLevel);
        } catch (Throwable $e) {
            $failure = $e;
        }

        $this->transactions = $toLevel;

        if ($this->transactionsManager) {
            $this->transactionsManager->rollback(
                $this->getName(),
                $this->transactions
            );
        }

        $this->fireConnectionEvent('rollingBack');

        if ($failure) {
            $this->handleRollBackException($failure);
        }
    }

    /**
     * Perform rollback.
     */
    protected function performRollBack($toLevel)
    {
        // Each ROLLBACK closes one level there, the outermost included.
        if ($this->nestsTransactionsItself()) {
            for ($level = $this->transactions; $level > $toLevel; $level--) {
                $this->transactionStatement('ROLLBACK;');
            }

            return;
        }

        if ($toLevel === 0) {

            if ($this->inTransaction()) {
                $this->transactionStatement('ROLLBACK;');
            }

        } elseif ($this->queryGrammar->supportsSavepoints()) {

            $name = $this->getSavepointName($toLevel + 1);
            $this->transactionStatement(
                $this->queryGrammar->compileSavepointRollBack($name) . ';'
            );
        }
    }

    /**
     * Handle transaction exception.
     */
    protected function handleTransactionException(Throwable $e, $currentAttempt, $maxAttempts)
    {
        // A deadlock undoes the whole transaction in MySQL, the outer one
        // too, so a nested transaction can't run again on its own: the
        // outer one decides whether to retry.
        if ($this->causedByConcurrencyError($e) && $this->transactions > 1) {
            $this->transactions--;

            if ($this->transactionsManager) {
                $this->transactionsManager->rollback(
                    $this->getName(),
                    $this->transactions
                );
            }

            throw new DeadlockException(
                $e->getMessage(), is_int($e->getCode()) ? $e->getCode() : 0, $e
            );
        }

        // The callback's exception is what the caller needs, even when the
        // rollback fails too: the database may have undone the work itself
        // (SQLite drops a savepoint when a statement in it fails).
        try {
            $this->rollBack();
        } catch (Throwable $rollBackFailure) {
            //
        }

        // A lock clash may not happen again: run the callback once more.
        if ($this->causedByConcurrencyError($e) && $currentAttempt < $maxAttempts) {
            return;
        }

        throw $e;
    }

    /**
     * Handle commit exception.
     */
    protected function handleCommitTransactionException(Throwable $e, $currentAttempt, $maxAttempts)
    {
        $this->transactions = max(0, $this->transactions - 1);

        if ($this->causedByConcurrencyError($e) && $currentAttempt < $maxAttempts) {
            return;
        }

        if ($this->causedByLostConnection($e)) {
            $this->transactions = 0;
        }

        throw $e;
    }

    /**
     * Handle begin exception.
     */
    protected function handleBeginTransactionException(Throwable $e)
    {
        if (! $this->causedByLostConnection($e)) {
            throw $e;
        }

        $this->reconnect();

        $this->transactionStatement('START TRANSACTION;');
    }

    /**
     * Handle rollback exception.
     */
    protected function handleRollBackException(Throwable $e)
    {
        if ($this->causedByLostConnection($e)) {
            $this->transactions = 0;
        }

        throw $e;
    }

    /**
     * Get savepoint name.
     */
    protected function getSavepointName($level)
    {
        return 'trans' . $level;
    }

    /**
     * Transaction nesting level.
     */
    public function transactionLevel()
    {
        return $this->transactions;
    }

    /**
     * After commit callback.
     */
    public function afterCommit($callback)
    {
        if ($this->transactionsManager) {
            return $this->transactionsManager->addCallback($callback);
        }

        throw new RuntimeException('Transactions Manager has not been set.');
    }

    /**
     * In transaction?
     */
    public function inTransaction()
    {
        return $this->transactions > 0;
    }

    /**
     * Set transaction manager.
     */
    public function setTransactionManager($manager)
    {
        $this->transactionsManager = $manager;
        return $this;
    }

    /**
     * Unset transaction manager.
     */
    public function unsetTransactionManager()
    {
        $this->transactionsManager = null;
    }
}
