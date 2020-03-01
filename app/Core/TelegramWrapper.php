<?php

namespace EmojiExperts\Core;

use EmojiExperts\Traits\Cacheable;
use EmojiExperts\Traits\Logable;
use Exception;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\TelegramLog;
use Monolog\Logger;

class TelegramWrapper
{
    use Logable, Cacheable;
    /** @var string */
    protected $projectPath = '';
    /** @var string */
    protected $token = '';
    /** @var string */
    protected $botName = '';
    /** @var Logger */
    protected $logger;
    /** @var Telegram */
    private $bot;

    /**
     * TelegramWrapper constructor.
     * @param string $projectPath
     * @param string $token
     * @param string $botName
     * @param Logger $logger
     */
    public function __construct(string $projectPath, string $token, string $botName, Logger $logger)
    {
        $this->projectPath = $projectPath;
        $this->token = $token;
        $this->botName = $botName;
        $this->logger = $logger;
    }

    /**
     * @param bool $cli
     * @throws TelegramException
     */
    public function init(bool $cli = false): void
    {
        $this->bot = new Telegram($this->token, $this->botName);
        $this->register($this->botName, $cli);
        TelegramLog::initialize($this->logger);
        $this->bot->enableAdmin(intval(getenv('ADMIN')));
        $this->bot->addCommandsPaths([
            $this->projectPath . '/app/Commands/',
        ]);

        $this->bot->enableExternalMySql(App::get('db'));
        $this->bot->enableLimiter();
        if ($cli === true) {
            $this->bot->handleGetUpdates();
        } else {
            $this->bot->handle();
        }

    }

    /**
     * @param string $prefix
     * @param bool $cli
     * @throws Exception
     */
    private function register(string $prefix, bool $cli = false): void
    {
        $key = $prefix . '_registered';
        if (!$this->cache()->exists($key)) {
            if ($cli === false) {
                try {
                    $hook_url = "https://" . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
                    $result = $this->bot->setWebhook($hook_url);
                    if ($result->isOk()) {
                        $this->cache()->set($key, $result->getDescription());
                    }
                } catch (TelegramException $e) {
                    $this->logger()->error('Registered failed', ['error' => $e->getMessage()]);
                }
            } else {
                $this->bot->deleteWebhook();
                $del = $this->cache()->del([$key]);
                $this->logger()->error('Registered failed', [
                    'cli' => $cli, 'del' => $del,
                'exist' => $this->cache()->exists($key)]);
            }
        }
    }
}