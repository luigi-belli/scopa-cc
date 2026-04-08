export default {
  // Lobby
  'lobby.title': 'Scopa',
  'lobby.subtitle': 'The Italian card game',
  'lobby.playerName': 'Your name',
  'lobby.playerNamePlaceholder': 'Enter your name',
  'lobby.gameName': 'Game name',
  'lobby.gameNamePlaceholder': 'Choose a game name',
  'lobby.deckStyle': 'Card style',
  'lobby.language': 'Language',
  'lobby.newGame': 'New Game',
  'lobby.join': 'Join',
  'lobby.twoPlayers': '2 Players',
  'lobby.onePlayer': '1 Player',
  'lobby.playClaude': 'Play against Claude',
  'lobby.errorNameRequired': 'Please enter your name.',
  'lobby.errorGameNameRequired': 'Please enter the game name.',
  'lobby.errorGameNotFound': 'Game not found',

  // Waiting
  'waiting.title': 'Waiting for opponent...',
  'waiting.share': 'Share the game name with your opponent',
  'waiting.back': 'Back to lobby',

  // Game
  'game.exit': 'Leave game',
  'game.exitConfirm': 'Are you sure you want to leave the game?',
  'game.pts': 'pts',
  'game.scope': 'scope',

  // Turn indicator
  'turn.mine': 'Your turn',
  'turn.opponent': 'Opponent\'s turn',

  // Capture choice
  'capture.title': 'Choose capture',

  // Round end
  'round.end': 'Round over',
  'round.next': 'Next round',

  // Game over
  'gameover.won': 'You Won! 🎉',
  'gameover.lost': 'You Lost',
  'gameover.finalScore': 'Final score: {my} - {opp}',
  'gameover.newGame': 'New game',

  // Score table
  'score.carte': 'Cards',
  'score.denari': 'Coins',
  'score.setteBello': 'Sette Bello',
  'score.primiera': 'Primiera',
  'score.scope': 'Scope',
  'score.roundTotal': 'Round total',
  'score.total': 'Score',

  // Effects
  'effect.scopa': 'SCOPA!',
  'effect.disconnect': 'Opponent disconnected. You win!',

  // Cards
  'card.back': 'Card back',
  'card.alt': '{value} of {suit}',
  'suit.Denari': 'Coins',
  'suit.Coppe': 'Cups',
  'suit.Bastoni': 'Clubs',
  'suit.Spade': 'Swords',

  // Deck names
  'deck.piacentine': 'Piacentine',
  'deck.napoletane': 'Napoletane',
  'deck.toscane': 'Toscane',
  'deck.siciliane': 'Siciliane',

  // API/backend error keys
  'error.conflict': 'Conflict: please retry',
  'error.gameNameTaken': 'A game with this name already exists. Please choose a different name.',
  'api.accessDenied': 'Access denied: invalid token',
  'api.error': 'API error {status}: {text}',
} as const satisfies Record<string, string>
