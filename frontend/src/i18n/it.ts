export default {
  // Lobby
  'lobby.title': 'Scopa',
  'lobby.subtitle': 'Il gioco di carte italiano',
  'lobby.playerName': 'Il tuo nome',
  'lobby.playerNamePlaceholder': 'Inserisci il tuo nome',
  'lobby.gameName': 'Nome della partita',
  'lobby.gameNamePlaceholder': 'Scegli un nome per la partita',
  'lobby.deckStyle': 'Stile delle carte',
  'lobby.language': 'Lingua',
  'lobby.newGame': 'Nuova Partita',
  'lobby.join': 'Unisciti',
  'lobby.twoPlayers': '2 Giocatori',
  'lobby.onePlayer': '1 Giocatore',
  'lobby.playClaude': 'Gioca contro Claude',
  'lobby.errorNameRequired': 'Inserisci il tuo nome.',
  'lobby.errorGameNameRequired': 'Inserisci il nome della partita.',
  'lobby.errorGameNotFound': 'Partita non trovata',

  // Waiting
  'waiting.title': 'In attesa dell\'avversario...',
  'waiting.share': 'Condividi il nome della partita con il tuo avversario',
  'waiting.back': 'Torna alla lobby',

  // Game
  'game.exit': 'Esci dalla partita',
  'game.exitConfirm': 'Sei sicuro di voler abbandonare la partita?',
  'game.pts': 'pts',
  'game.scope': 'scope',

  // Turn indicator
  'turn.mine': 'Il tuo turno',
  'turn.opponent': 'Turno avversario',

  // Capture choice
  'capture.title': 'Scegli la cattura',

  // Round end
  'round.end': 'Fine del turno',
  'round.next': 'Prossimo turno',

  // Game over
  'gameover.won': 'Hai Vinto! 🎉',
  'gameover.lost': 'Hai Perso',
  'gameover.finalScore': 'Punteggio finale: {my} - {opp}',
  'gameover.newGame': 'Nuova partita',

  // Score table
  'score.carte': 'Carte',
  'score.denari': 'Denari',
  'score.setteBello': 'Sette Bello',
  'score.primiera': 'Primiera',
  'score.scope': 'Scope',
  'score.roundTotal': 'Totale turno',
  'score.total': 'Punteggio',

  // Effects
  'effect.scopa': 'SCOPA!',
  'effect.disconnect': 'L\'avversario si è disconnesso. Hai vinto!',

  // Dialog
  'dialog.close': 'Chiudi',

  // Cards
  'card.back': 'Retro della carta',
  'card.alt': '{value} di {suit}',
  'suit.Denari': 'Denari',
  'suit.Coppe': 'Coppe',
  'suit.Bastoni': 'Bastoni',
  'suit.Spade': 'Spade',

  // Deck names
  'deck.piacentine': 'Piacentine',
  'deck.napoletane': 'Napoletane',
  'deck.toscane': 'Toscane',
  'deck.siciliane': 'Siciliane',

  // API/backend error keys
  'error.conflict': 'Conflitto: riprova',
  'error.gameNameTaken': 'Esiste già una partita con questo nome. Scegline un altro.',
  'api.accessDenied': 'Accesso negato: token non valido',
  'api.error': 'Errore API {status}: {text}',
} as const satisfies Record<string, string>
