<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Dto\Input\CreateGameInput;
use App\Dto\Input\JoinGameInput;
use App\Dto\Input\PlayCardInput;
use App\Dto\Input\SelectCaptureInput;
use App\Dto\Output\CreateGameOutput;
use App\Dto\Output\GameLookupOutput;
use App\Dto\Output\GameStateOutput;
use App\Dto\Output\JoinGameOutput;
use App\Enum\GameState;
use App\State\Processor\CreateGameProcessor;
use App\State\Processor\HeartbeatProcessor;
use App\State\Processor\JoinGameProcessor;
use App\State\Processor\LeaveGameProcessor;
use App\State\Processor\NextRoundProcessor;
use App\State\Processor\PlayCardProcessor;
use App\State\Processor\SelectCaptureProcessor;
use App\State\Provider\GameLookupProvider;
use App\State\Provider\GameStateProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'games')]
#[ORM\Index(columns: ['state'], name: 'idx_game_state')]
#[ORM\UniqueConstraint(name: 'uniq_game_name', columns: ['name'])]
#[ApiResource(
    shortName: 'Game',
    operations: [
        new Post(
            uriTemplate: '/games',
            input: CreateGameInput::class,
            output: CreateGameOutput::class,
            processor: CreateGameProcessor::class,
        ),
        new Get(
            uriTemplate: '/games/{id}',
            output: GameStateOutput::class,
            provider: GameStateProvider::class,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
        new GetCollection(
            uriTemplate: '/games/lookup',
            output: GameLookupOutput::class,
            provider: GameLookupProvider::class,
        ),
        new Post(
            name: 'join',
            uriTemplate: '/games/{id}/join',
            input: JoinGameInput::class,
            output: JoinGameOutput::class,
            processor: JoinGameProcessor::class,
            read: false,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
        new Post(
            name: 'play_card',
            uriTemplate: '/games/{id}/play-card',
            input: PlayCardInput::class,
            output: GameStateOutput::class,
            processor: PlayCardProcessor::class,
            read: false,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
        new Post(
            name: 'select_capture',
            uriTemplate: '/games/{id}/select-capture',
            input: SelectCaptureInput::class,
            output: GameStateOutput::class,
            processor: SelectCaptureProcessor::class,
            read: false,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
        new Post(
            name: 'next_round',
            uriTemplate: '/games/{id}/next-round',
            output: GameStateOutput::class,
            processor: NextRoundProcessor::class,
            read: false,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
        new Post(
            name: 'heartbeat',
            uriTemplate: '/games/{id}/heartbeat',
            output: false,
            processor: HeartbeatProcessor::class,
            read: false,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
        new Post(
            name: 'leave',
            uriTemplate: '/games/{id}/leave',
            output: false,
            processor: LeaveGameProcessor::class,
            read: false,
            requirements: ['id' => '[0-9a-f-]{36}'],
        ),
    ],
)]
class Game
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', enumType: GameState::class)]
    private GameState $state = GameState::Waiting;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $player1Token = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $player2Token = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $player1Name = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $player2Name = null;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $player1Hand = [];

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $player2Hand = [];

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $tableCards = [];

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $deck = [];

    #[ORM\Column(type: 'integer')]
    private int $currentPlayer = 0;

    #[ORM\Column(type: 'integer')]
    private int $dealerIndex = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lastCapturer = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    private ?array $pendingPlay = null;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $player1Captured = [];

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $player2Captured = [];

    #[ORM\Column(type: 'integer')]
    private int $player1Scope = 0;

    #[ORM\Column(type: 'integer')]
    private int $player2Scope = 0;

    #[ORM\Column(type: 'integer')]
    private int $player1TotalScore = 0;

    #[ORM\Column(type: 'integer')]
    private int $player2TotalScore = 0;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $roundHistory = [];

    #[ORM\Column(type: 'string', length: 20)]
    private string $deckStyle = 'piacentine';

    #[ORM\Column(type: 'boolean')]
    private bool $singlePlayer = false;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastHeartbeat1 = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastHeartbeat2 = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getState(): GameState
    {
        return $this->state;
    }

    public function setState(GameState $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getPlayer1Token(): ?string
    {
        return $this->player1Token;
    }

    public function setPlayer1Token(?string $token): self
    {
        $this->player1Token = $token;
        return $this;
    }

    public function getPlayer2Token(): ?string
    {
        return $this->player2Token;
    }

    public function setPlayer2Token(?string $token): self
    {
        $this->player2Token = $token;
        return $this;
    }

    public function getPlayer1Name(): ?string
    {
        return $this->player1Name;
    }

    public function setPlayer1Name(?string $name): self
    {
        $this->player1Name = $name;
        return $this;
    }

    public function getPlayer2Name(): ?string
    {
        return $this->player2Name;
    }

    public function setPlayer2Name(?string $name): self
    {
        $this->player2Name = $name;
        return $this;
    }

    public function getPlayer1Hand(): array
    {
        return $this->player1Hand;
    }

    public function setPlayer1Hand(array $hand): self
    {
        $this->player1Hand = $hand;
        return $this;
    }

    public function getPlayer2Hand(): array
    {
        return $this->player2Hand;
    }

    public function setPlayer2Hand(array $hand): self
    {
        $this->player2Hand = $hand;
        return $this;
    }

    public function getPlayerHand(int $index): array
    {
        return $index === 0 ? $this->player1Hand : $this->player2Hand;
    }

    public function setPlayerHand(int $index, array $hand): self
    {
        if ($index === 0) {
            $this->player1Hand = $hand;
        } else {
            $this->player2Hand = $hand;
        }
        return $this;
    }

    public function getTableCards(): array
    {
        return $this->tableCards;
    }

    public function setTableCards(array $cards): self
    {
        $this->tableCards = $cards;
        return $this;
    }

    public function getDeck(): array
    {
        return $this->deck;
    }

    public function setDeck(array $deck): self
    {
        $this->deck = $deck;
        return $this;
    }

    public function getCurrentPlayer(): int
    {
        return $this->currentPlayer;
    }

    public function setCurrentPlayer(int $player): self
    {
        $this->currentPlayer = $player;
        return $this;
    }

    public function getDealerIndex(): int
    {
        return $this->dealerIndex;
    }

    public function setDealerIndex(int $index): self
    {
        $this->dealerIndex = $index;
        return $this;
    }

    public function getLastCapturer(): ?int
    {
        return $this->lastCapturer;
    }

    public function setLastCapturer(?int $capturer): self
    {
        $this->lastCapturer = $capturer;
        return $this;
    }

    public function getPendingPlay(): ?array
    {
        return $this->pendingPlay;
    }

    public function setPendingPlay(?array $play): self
    {
        $this->pendingPlay = $play;
        return $this;
    }

    public function getPlayer1Captured(): array
    {
        return $this->player1Captured;
    }

    public function setPlayer1Captured(array $captured): self
    {
        $this->player1Captured = $captured;
        return $this;
    }

    public function getPlayer2Captured(): array
    {
        return $this->player2Captured;
    }

    public function setPlayer2Captured(array $captured): self
    {
        $this->player2Captured = $captured;
        return $this;
    }

    public function getPlayerCaptured(int $index): array
    {
        return $index === 0 ? $this->player1Captured : $this->player2Captured;
    }

    public function setPlayerCaptured(int $index, array $captured): self
    {
        if ($index === 0) {
            $this->player1Captured = $captured;
        } else {
            $this->player2Captured = $captured;
        }
        return $this;
    }

    public function getPlayer1Scope(): int
    {
        return $this->player1Scope;
    }

    public function setPlayer1Scope(int $scope): self
    {
        $this->player1Scope = $scope;
        return $this;
    }

    public function getPlayer2Scope(): int
    {
        return $this->player2Scope;
    }

    public function setPlayer2Scope(int $scope): self
    {
        $this->player2Scope = $scope;
        return $this;
    }

    public function getPlayerScope(int $index): int
    {
        return $index === 0 ? $this->player1Scope : $this->player2Scope;
    }

    public function setPlayerScope(int $index, int $scope): self
    {
        if ($index === 0) {
            $this->player1Scope = $scope;
        } else {
            $this->player2Scope = $scope;
        }
        return $this;
    }

    public function getPlayer1TotalScore(): int
    {
        return $this->player1TotalScore;
    }

    public function setPlayer1TotalScore(int $score): self
    {
        $this->player1TotalScore = $score;
        return $this;
    }

    public function getPlayer2TotalScore(): int
    {
        return $this->player2TotalScore;
    }

    public function setPlayer2TotalScore(int $score): self
    {
        $this->player2TotalScore = $score;
        return $this;
    }

    public function getPlayerTotalScore(int $index): int
    {
        return $index === 0 ? $this->player1TotalScore : $this->player2TotalScore;
    }

    public function setPlayerTotalScore(int $index, int $score): self
    {
        if ($index === 0) {
            $this->player1TotalScore = $score;
        } else {
            $this->player2TotalScore = $score;
        }
        return $this;
    }

    public function getRoundHistory(): array
    {
        return $this->roundHistory;
    }

    public function setRoundHistory(array $history): self
    {
        $this->roundHistory = $history;
        return $this;
    }

    public function getDeckStyle(): string
    {
        return $this->deckStyle;
    }

    public function setDeckStyle(string $style): self
    {
        $this->deckStyle = $style;
        return $this;
    }

    public function isSinglePlayer(): bool
    {
        return $this->singlePlayer;
    }

    public function setSinglePlayer(bool $single): self
    {
        $this->singlePlayer = $single;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getLastHeartbeat1(): ?\DateTimeImmutable
    {
        return $this->lastHeartbeat1;
    }

    public function setLastHeartbeat1(?\DateTimeImmutable $time): self
    {
        $this->lastHeartbeat1 = $time;
        return $this;
    }

    public function getLastHeartbeat2(): ?\DateTimeImmutable
    {
        return $this->lastHeartbeat2;
    }

    public function setLastHeartbeat2(?\DateTimeImmutable $time): self
    {
        $this->lastHeartbeat2 = $time;
        return $this;
    }

    public function getPlayerToken(int $index): ?string
    {
        return $index === 0 ? $this->player1Token : $this->player2Token;
    }

    public function getPlayerName(int $index): ?string
    {
        return $index === 0 ? $this->player1Name : $this->player2Name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvePlayerIndex(string $token): ?int
    {
        if ($this->player1Token !== null && hash_equals($this->player1Token, $token)) {
            return 0;
        }
        if ($this->player2Token !== null && hash_equals($this->player2Token, $token)) {
            return 1;
        }
        return null;
    }

    public function isLastPlayOfRound(): bool
    {
        return count($this->deck) === 0
            && count($this->player1Hand) === 0
            && count($this->player2Hand) === 0;
    }
}
