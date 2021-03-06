<?php

namespace EmojiExperts\Core;

use Carbon\Carbon;
use Slim\PDO\Database;

/**
 * select DATE(u.created_at) as date, count(u.created_at) as invited from user as u group by date;
 *
 * select u.username, u.last_name, u.first_name, sum(g.score)/count(g.user_id) from games as g  join user as u on u.id = g.user_id group by g.user_id
 *
 * Class DbRepository
 * @package EmojiExperts\Core
 */
class DbRepository
{
    const YES_NO_GAME_MODE = 0;

    const RIDDLE_GAME_MODE = 1;

    const STATUS_GAME_OVER = 0;

    const STATUS_IN_PROGRESS = 1;
    /**
     * @var Database
     */
    private $connection;

    public function __construct(Database $database)
    {
        $this->connection = $database;
    }


    /**
     * @param string $category
     * @param string $subcategory
     * @return array
     */
    public function getEmoji(string $category, string $subcategory): array
    {
        $selectStatement = $this->connection->select([
            'emoji',
            'name',
        ])
            ->from('emoji_fix')
            ->where('category', '=', $category)
            ->where('subcategory', '=', $subcategory)
        ;
        $stmt = $selectStatement->execute();
        $fetch = $stmt->fetchAll();

        return $fetch ?? [];
    }

    public function getLeaders(): array
    {
        $selectStatement = $this->connection->select([
            'u.username',
            'u.first_name',
            'u.last_name',
            'MAX(g.score) as score',
            'g.status',
        ])
            ->from('games g')
            ->join('user u', 'u.id', '=', 'g.user_id')
            ->orderBy('score', 'DESC')
            ->orderBy('g.created_at', 'DESC')
            ->groupBy('u.id')
            ->limit(20)
        ;
        $stmt = $selectStatement->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param string $emoji
     * @param string $codes
     * @param string $name
     * @param string $category
     * @param string $subcategory
     * @return array
     */
    public function insertEmoji(
        string $emoji,
        string $codes,
        string $name,
        string $category,
        string $subcategory
    ): array {
        $result = [
            $emoji,
            $codes,
            $name,
            $category,
            $subcategory
        ];
        $insertStatement = $this->connection->insert([
            'emoji',
            'codes',
            'name',
            'category',
            'subcategory',
        ])
            ->into('emoji_fix')
            ->values($result);
        $insertStatement->execute(false);

        return [$result];
    }


    public function getCategories()
    {
        $selectStatement = $this->connection->select([
            'category', 'subcategory'
        ])
            ->from('emoji_fix')
            ->distinct()
            ->whereNotLike('category', 'Flags')
            ->whereNotLike('category', 'Symbols')
        ;
        $stmt = $selectStatement->execute();
        $fetch = $stmt->fetchAll();

        return $fetch ?? [];

    }

    public function startNewGame(int $id, int $mode): array
    {
        $insertStatement = $this->connection->insert([
            'user_id',
            'mode',
        ])
            ->into('games')
            ->values([
                $id,
                $mode
            ]);
        $newId = $insertStatement->execute();

        $updateStatement = $this->connection->update([
            'updated_at' => Carbon::now(),
            'game_id' => $newId
        ])
            ->table('games')
            ->where('id', '=', $newId)
        ;
        $affectedRows = $updateStatement->execute();


        return ['id' => $newId, 'score' => 0];
    }

    public function updateGame(int $userId, int $gameId, int $score, int $mode)
    {
        $updateStatement = $this->connection->insert([
            'game_id' => $gameId,
            'user_id' => $userId,
            'score' => $score,
            'mode' => $mode,
            'updated_at' => Carbon::now()
        ])
            ->into('games')
        ;
        $updateStatement->execute();
    }

    public function getGameById($gameId)
    {
        $selectStatement = $this->connection->select([
            'id',
            'score',
            'created_at',
            'updated_at',
        ])
            ->from('games')
            ->where('game_id', '=', $gameId)
            ->orderBy('created_at', 'DESC')
            ->limit(1);
        ;
        $stmt = $selectStatement->execute();
        $fetch = $stmt->fetchAll();

        return $fetch[0] ?? [];
    }
}