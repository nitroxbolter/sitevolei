function requestFullscreenMode(lockLandscape = false) {
    const root = document.documentElement;
    const goFullscreen = document.fullscreenElement || root.requestFullscreen === undefined ? Promise.resolve() : root.requestFullscreen();

    Promise.resolve(goFullscreen)
        .then(() => {
            if (lockLandscape && screen.orientation && screen.orientation.lock) {
                return screen.orientation.lock("landscape");
            }
            return null;
        })
        .catch(() => {});
}

function requestMobileLandscape() {
    const isTouchDevice = window.matchMedia("(hover: none) and (pointer: coarse)").matches || navigator.maxTouchPoints > 0;
    if (isTouchDevice === false) return;

    requestFullscreenMode(true);
}

["pointerdown", "touchstart", "click"].forEach((eventName) => {
    window.addEventListener(eventName, requestMobileLandscape, { once: true, passive: false });
});

function requestMobileFullscreenWhenPossible() {
    requestMobileLandscape();
}

document.addEventListener("DOMContentLoaded", () => {
    requestMobileFullscreenWhenPossible();
    initAdminDebugLogModal();
    initCampaignSelector();

    const fullscreenButton = document.getElementById("fullscreen-button");
    if (fullscreenButton) {
        fullscreenButton.addEventListener("click", () => requestFullscreenMode(true));
    }
});

window.addEventListener("load", requestMobileFullscreenWhenPossible, { once: true });

function isDesktopDebugUiAllowed() {
    const touchOnly = window.matchMedia("(hover: none) and (pointer: coarse)").matches || navigator.maxTouchPoints > 0;
    return isAdminDebugEnabled() && !touchOnly;
}

function buildCurrentAdminDebugText() {
    if (!isAdminDebugEnabled()) return 'Debug disponivel somente para admin.';

    const parts = [
        `adversario atual: ${getCurrentOpponent().name.toUpperCase()}`,
        `dificuldade atual: ${getDifficultyProfile().label.toUpperCase()}`
    ];
    if (adminDebugLogHistory.length) {
        parts.push(adminDebugLogHistory.join('\n\n================ HISTORICO =================\n\n'));
    }

    if (rallyLog) {
        const current = [...rallyLog.lines];
        completedShotLogs.forEach((shot, index) => current.push(formatShotLog(shot, index + 1)));
        if (activeShotLog) {
            current.push(formatShotLog({ ...activeShotLog, result: activeShotLog.result || 'em andamento' }, completedShotLogs.length + 1));
        }
        current.push(`placar atual: jogador ${score.player} x ${score.opponent} adversario`);
        current.push(`sets atuais: jogador ${score.playerSets} x ${score.opponentSets} adversario`);
        current.push(`estado atual: ${state}`);
        parts.push(current.join('\n'));
    } else if (lastCompletedLogText && !adminDebugLogHistory.length) {
        parts.push(lastCompletedLogText);
    }

    if (lastDebugSavePath) parts.push(`ultimo arquivo salvo: ${lastDebugSavePath}`);
    return parts.length ? parts.join('\n\n') : 'Nenhum log iniciado ainda. Inicie um saque para começar o debug.';
}

function updateAdminDebugLogModal() {
    const content = document.getElementById('debug-log-content');
    if (!content) return;
    content.textContent = buildCurrentAdminDebugText();
}

function clearAdminDebugLogModal() {
    adminDebugLogHistory = [];
    rallyLog = null;
    activeShotLog = null;
    completedShotLogs = [];
    lastCompletedLogText = '';
    lastCompletedLogName = 'volei-log-ultimo.txt';
    lastDebugSavePath = '';
    try {
        localStorage.removeItem('ultimoLogVolei');
    } catch (_err) {}
    updateAdminDebugLogModal();
}

function initAdminDebugLogModal() {
    if (!isDesktopDebugUiAllowed()) return;
    const modal = document.getElementById('debug-log-modal');
    const openButton = document.getElementById('debug-log-button');
    const closeButton = document.getElementById('debug-log-close');
    const clearButton = document.getElementById('debug-log-clear');
    const refreshButton = document.getElementById('debug-log-refresh');
    if (!modal || !openButton) return;

    const openModal = () => {
        updateAdminDebugLogModal();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    };
    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };

    openButton.addEventListener('click', openModal);
    if (closeButton) closeButton.addEventListener('click', closeModal);
    if (refreshButton) refreshButton.addEventListener('click', updateAdminDebugLogModal);
    if (clearButton) clearButton.addEventListener('click', clearAdminDebugLogModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
}

const config = {
    type: Phaser.AUTO,
    width: 800,
    height: 600,
    parent: 'game-container',
    scale: {
        mode: Phaser.Scale.FIT,
        autoCenter: Phaser.Scale.CENTER_BOTH
    },
    physics: {
        default: 'arcade',
        arcade: {
            gravity: { y: 0 },
            debug: false
        }
    },
    scene: { preload, create, update }
};

const SHOW_DEBUG = false;
const SHOW_TRAJECTORY = false;

new Phaser.Game(config);

const NET = {
    // Calibrated to the net line in `quadra.png`
    y: 320,
    // Visual top of net is ~82px above the net line; convert px -> Z via zToPixels.
    heightZ: 114,
    // Visual-only: extra pixels to align the red ruler with the net in the background image.
    markerExtraPx: 18,
    markerX: 748
};

const COURT = {
    width: 800,
    height: 600,
    centerX: 400,
    playerStartX: 400,
    playerStartY: 512,
    minX: 92,
    maxX: 708,
    // Limites do lado do jogador (Y em tela).
    // Rede (profundidade 0) fica em NET.y; jogador não deve ultrapassar a rede.
    minY: NET.y,
    // Profundidade +250 (fim da quadra do jogador) => NET.y + 250.
    maxY: NET.y + 250
};

const PLAYER = {
    size: 128,
    // Ajustes de âncora do "topo do jogador" (para régua e para posicionar a bola ao levantar).
    // Se a bola/régua estiverem "saindo das costas", aumente `headOffsetY` e/ou `ballAboveHead`.
    headOffsetY: 92,
    ballAboveHead: 26,
    speedX: 250,
    speedY: 185,
    serveSpeedY: 210
};

const RECEIVE = {
    // Ajuste fino do "alcance" da manchete (distância no chão / screen space)
    radius: 48,
    minZ: 5,
    maxZ: 64
};

const BALL = {
    size: 32,
    zToPixels: 0.72,
    // Z physics is integrated with dt (seconds). These are per-second^2 accelerations.
    // Converted from older per-frame-at-60fps tuning: a_sec ~= a_frame * 60.
    gravityZ: -13.2,
    gravityZToss: -440,
    gravityZFlightPreNet: -260,
    gravityZFlightPostNet: -340,
    tossTargetZ: 130,
    hitWindowZ: 118,
    hitMinZ: 20,
    serveSideVelocity: 140
};

const LIFT_CHARGE = {
    maxMs: 1000,
    minTossZ: 50,
    maxTossZ: 130,
    // Initial upward impulse derived from target height; scaled for feel.
    vzScale: 1.0
};

const ATTACK = {
    // Attack is instant; quality depends on timing (how close to hitWindowZ) and ball height.
    goodWindowZ: 14,
    midWindowZ: 30,
    weak: { label: 'RUIM', forward: -300, up: 150, netAssist: 0, shake: 0.006 },
    mid: { label: 'MEDIO', forward: -470, up: 220, netAssist: 30, shake: 0.012 },
    good: { label: 'BOM', forward: -660, up: 285, netAssist: 100, shake: 0.016 }
};

const OPPONENT_COURT = {
    // Approximate playable zone on the opponent side (in screen Y).
    // Values are tuned to the `quadra.png` perspective.
    minY: 78,
    maxY: 304,
    backLineY: 144,
    backMinX: 210,
    backMaxX: 597,
    netLineY: 320,
    netMinX: 155,
    netMaxX: 645
};

const OPPONENT_AI = {
    setRadius: 62,
    attackRadius: 52,
    zoneSplitX: 400,
    zoneSplitY: 198,
    backHome: { x: 400, y: 164, rangeX: 305, rangeY: 58, zone: { minX: 92, maxX: 708, minY: 70, maxY: 198 } },
    leftHome: { x: 250, y: 244, rangeX: 108, rangeY: 64, zone: { minX: 92, maxX: 400, minY: 198, maxY: 320 } },
    rightHome: { x: 550, y: 244, rangeX: 108, rangeY: 64, zone: { minX: 400, maxX: 708, minY: 198, maxY: 320 } },
    setterHome: { x: 400, y: 304 },
    serverHome: { x: 400, y: 176 }
};

const VOLLEYBALL_DIMENSIONS = {
    courtLengthMeters: 18,
    courtWidthMeters: 9,
    halfCourtMeters: 9,
    netHeightMeters: 2.43
};

const DIFFICULTIES = Object.freeze({
    easy: {
        label: 'Fácil',
        receiveRadius: 38,
        serveReceiveBonus: 10,
        reactionDelayMs: 260,
        trackRateServe: 5.4,
        trackRateAttack: 4.2,
        lowServeSetterChance: 0.3,
        receive: {
            serve: { base: 0.74, powerPenalty: 0.24, distancePenalty: 0.18, min: 0.38, max: 0.76, closeMin: 0.72 },
            attack: { base: 0.48, powerPenalty: 0.42, distancePenalty: 0.28, min: 0.1, max: 0.5 }
        },
        receiveMissOutChance: { serve: 0.62, attack: 0.7 },
        passFlightTime: 1.02,
        setFlightTime: 1.62,
        serve: { flightTime: 1.5, spreadX: 42, minDepth: 165, maxDepth: 205, minBallZ: 118 },
        attack: { flightTime: 1.55, spreadX: 85, minDepth: 155, maxDepth: 205, contactZ: 100, errorChance: 0.28 }
    },
    medium: {
        label: 'Médio',
        receiveRadius: 48,
        serveReceiveBonus: 18,
        reactionDelayMs: 90,
        trackRateServe: 14.9,
        trackRateAttack: 7.7,
        lowServeSetterChance: 0.75,
        receive: {
            serve: { base: 0.98, powerPenalty: 0.12, distancePenalty: 0.04, min: 0.82, max: 0.99, closeMin: 0.97 },
            attack: { base: 0.62, powerPenalty: 0.38, distancePenalty: 0.18, min: 0.25, max: 0.78 }
        },
        receiveMissOutChance: { serve: 0.08, attack: 0.35 },
        passFlightTime: 0.82,
        setFlightTime: 1.45,
        serve: { flightTime: 1.18, spreadX: 90, minDepth: 155, maxDepth: 235, minBallZ: 112 },
        attack: { flightTime: 1.25, spreadX: 190, minDepth: 150, maxDepth: 240, contactZ: 112, errorChance: 0.08 }
    },
    advanced: {
        label: 'Avançado',
        receiveRadius: 60,
        serveReceiveBonus: 24,
        reactionDelayMs: 0,
        trackRateServe: 21,
        trackRateAttack: 15,
        lowServeSetterChance: 1,
        receive: {
            serve: { base: 1, powerPenalty: 0.07, distancePenalty: 0.02, min: 0.91, max: 0.995, closeMin: 0.99 },
            attack: { base: 0.94, powerPenalty: 0.2, distancePenalty: 0.1, min: 0.64, max: 0.95 }
        },
        receiveMissOutChance: { serve: 0.04, attack: 0.18 },
        passFlightTime: 0.68,
        setFlightTime: 1.18,
        serve: { flightTime: 0.94, spreadX: 145, minDepth: 190, maxDepth: 242, minBallZ: 142 },
        attack: { flightTime: 0.96, spreadX: 235, minDepth: 185, maxDepth: 245, contactZ: 128, errorChance: 0.01 }
    }
});

const OPPONENT_TEAMS = Object.freeze([
    { key: 'mexico', name: 'México', difficultyKey: 'easy', difficultyLabel: 'Fácil', skill: 0.88, color: '#35cf78' },
    { key: 'canada', name: 'Canadá', difficultyKey: 'easy', difficultyLabel: 'Fácil +', skill: 1.04, color: '#55d98d' },
    { key: 'spain', name: 'Espanha', difficultyKey: 'medium', difficultyLabel: 'Médio', skill: 0.92, color: '#ffd34d' },
    { key: 'usa', name: 'EUA', difficultyKey: 'medium', difficultyLabel: 'Médio +', skill: 1.04, color: '#ffbd3f' },
    { key: 'brazil', name: 'Brasil', difficultyKey: 'advanced', difficultyLabel: 'Avançado', skill: 0.96, color: '#ff786f' },
    { key: 'poland', name: 'Polônia', difficultyKey: 'advanced', difficultyLabel: 'Avançado +', skill: 1.05, color: '#ff5e5e' },
    { key: 'italy', name: 'Itália', difficultyKey: 'advanced', difficultyLabel: 'Elite', skill: 1.14, color: '#ff4040' }
]);

const PLAYER_HOME = {
    RECEPTOR: { x: COURT.playerStartX, y: COURT.playerStartY },
    SERVER: { x: COURT.centerX, y: 650 },
    LEFT: { x: 200, y: 420 },
    RIGHT: { x: 600, y: 420 },
    SETTER: { x: 400, y: 340 }
};

const DEPTH = {
    opponentBack: -250,
    playerBack: 300
};

const HIT_TRAJECTORY = {
    // Extra clearance above the net in Z-units.
    netClearanceZ: 12,
    // How strongly max charge can overshoot out of bounds (higher -> more "out" risk).
    overshootOutY: 160
};

const PHYS_CLAMP = {
    maxVX: 750,
    maxVY: 900,
    maxVZ: 800,
    minVZ: -800
};

let cursors;
let keys;
let virtualInput = {
    left: false,
    right: false,
    up: false,
    down: false,
    liftDown: false,
    liftPressed: false,
    liftReleased: false,
    receiveDown: false,
    jumpPressed: false,
    actionPressed: false
};
let touchControls = [];
let player;
let ball;
let ballBody;
let ballShadow;
let landingShadow;
let landingShadowState = 0; // 0 none, 1 sombra1, 2 sombra2
let trail;
let devGraphics;
let devText;
let devLabels = [];
let devPositionLabels = [];
let chargeBarBg;
let chargeBarFill;
let chargeBarText;
let statusText;
let statusTextEvent;
let trajectoryGraphics;
let playerRadiusGraphics;
let courtZoneGraphics;
let trajectoryLabels = [];
let trajectoryPoints = [];
let trajectoryNetMarkers = [];
let lastTrajectoryRecordMs = 0;
let debugShot = {
    maxZ: 0,
    hit: null,
    net: null,
    landing: null
};
let hasRecordedNetCrossing = false;
let closestNetCrossing = null;

// Physics bookkeeping (dt + robust collisions)
let prevBodyX = 0;
let prevBodyY = 0;
let prevBallZ = 0;
let prevBallVZ = 0;

// Shadow smoothing + hysteresis
let smoothedLandingX = 0;
let smoothedLandingY = 0;
let landingCloseToGround = false;

let ballZ = 0;
let ballVZ = 0;
let state = 'READY';
let difficultySelected = false;
let currentDifficultyKey = 'medium';
let difficultyProfileCache = null;
let activeMatchToken = null;
let matchResultSubmitting = false;
let unsavedMatchResult = null;
let campaignState = {
    loggedIn: Boolean(window.VOLEI_CAMPAIGN && window.VOLEI_CAMPAIGN.loggedIn),
    teamName: String(window.VOLEI_CAMPAIGN?.teamName || 'Meu Time'),
    opponentIndex: Number(window.VOLEI_CAMPAIGN?.opponentIndex || 0),
    wins: Number(window.VOLEI_CAMPAIGN?.wins || 0),
    losses: Number(window.VOLEI_CAMPAIGN?.losses || 0),
    campaignCompleted: Boolean(window.VOLEI_CAMPAIGN?.campaignCompleted)
};
let pointLocked = false;
let nextServer = 'PLAYER'; // PLAYER | OPPONENT
let gamePhase = 'SERVE'; // SERVE | RALLY
let lastTouch = 'NONE'; // NONE | PLAYER_PASS | PLAYER_SET | PLAYER_HIT | OPP_PASS | OPP_SET | OPP_HIT
let lastPlayerPassMs = -9999;
let lastContactMark = null; // { x, y, t }
let lastLandingMark = null; // { x, y, depth, width, t }
let lastOppAttackPlan = null; // debug: { fromX, fromY, toX, toY, vx, vy, flightTime, ballZ, ballVZ, t }
let debugPanelEl = null;
let playerHitPoseUntil = 0;
const MATCH = {
    regularSetPoints: 25,
    tieBreakPoints: 15,
    setsToWinMatch: 3,
    minPointLead: 2
};
let score = { player: 0, opponent: 0, playerSets: 0, opponentSets: 0 };
let playerPoseEvent;
let liftChargeStart = 0;
let hitPowerRatio = 0;
let queuedServeHitAt = 0;
let lastPlayerAttackPower = 0;
let lastPlayerShotKind = 'ATTACK';
let opponentReceiveAttemptedForPlayerHit = false;
let opponentDefenseStartedAtMs = 0;
let opponentLowServeDecision = null;
let opponentLetOutDecision = null;
let opponentLetOutPrediction = null;
let opponentLetOutLogged = false;
let opponentBack;
let opponentFront;
let opponentAttacker;
let playerBacker;
let playerSetter;
let playerLeft;
let playerRight;
let oppLeft;
let oppRight;
let playerZ = 0;
let isJumping = false;
let stepTimerMs = 0;
let stepToggle = false;
let rollDirY = 0;
let rollEndY = 0;
let jumpTween;
let controlledRole = 'RECEPTOR'; // RECEPTOR | LEFT | RIGHT
let desiredAttackRole = 'RIGHT'; // último atacante escolhido pelo levantamento: LEFT | RIGHT
let homePos = null;
let liftChoiceUi = null;
let controlledPlayer = null; // sprite físico atualmente controlado
let playerTeam = []; // [receptor, left, right]
let gameScene = null;
let opponentServeLiftEvent = null;
let opponentServeHitEvent = null;
let opponentPassToSetterEvent = null;
let opponentSetAttackEvent = null;
let rallyLog = null;
let rallyLogIndex = 0;
let activeShotLog = null;
let completedShotLogs = [];
let lastCompletedLogText = '';
let lastCompletedLogName = 'volei-log-ultimo.txt';
let lastLogSaveError = '';
let lastDebugSavePath = '';
let adminDebugLogHistory = [];

function isAdminDebugEnabled() {
    return Boolean(window.VOLEI_DEBUG_ADMIN);
}

function getDifficultyProfile() {
    const opponent = getCurrentOpponent();
    const base = DIFFICULTIES[opponent.difficultyKey] || DIFFICULTIES.medium;
    if (difficultyProfileCache?.opponentKey === opponent.key) return difficultyProfileCache.profile;

    const skill = opponent.skill;
    const paceFactor = 1 + ((skill - 1) * 0.4);
    const scaleChance = (value) => Phaser.Math.Clamp(value * skill, 0, 0.998);
    const scaleReceive = (values) => ({
        base: scaleChance(values.base),
        powerPenalty: values.powerPenalty / skill,
        distancePenalty: values.distancePenalty / skill,
        min: scaleChance(values.min),
        max: scaleChance(values.max),
        closeMin: scaleChance(values.closeMin)
    });

    const profile = {
        ...base,
        label: opponent.difficultyLabel,
        opponentName: opponent.name,
        receiveRadius: Math.round(base.receiveRadius * paceFactor),
        serveReceiveBonus: Math.round(base.serveReceiveBonus * paceFactor),
        reactionDelayMs: Math.max(0, Math.round(base.reactionDelayMs / skill)),
        trackRateServe: base.trackRateServe * paceFactor,
        trackRateAttack: base.trackRateAttack * paceFactor,
        lowServeSetterChance: scaleChance(base.lowServeSetterChance),
        receive: {
            serve: scaleReceive(base.receive.serve),
            attack: scaleReceive(base.receive.attack)
        },
        receiveMissOutChance: {
            serve: Phaser.Math.Clamp(base.receiveMissOutChance.serve / skill, 0.01, 0.95),
            attack: Phaser.Math.Clamp(base.receiveMissOutChance.attack / skill, 0.01, 0.95)
        },
        passFlightTime: base.passFlightTime / paceFactor,
        setFlightTime: base.setFlightTime / paceFactor,
        serve: {
            ...base.serve,
            flightTime: base.serve.flightTime / paceFactor,
            spreadX: base.serve.spreadX * paceFactor,
            minBallZ: base.serve.minBallZ * paceFactor
        },
        attack: {
            ...base.attack,
            flightTime: base.attack.flightTime / paceFactor,
            spreadX: base.attack.spreadX * paceFactor,
            contactZ: base.attack.contactZ * paceFactor,
            errorChance: Phaser.Math.Clamp(base.attack.errorChance / skill, 0.005, 0.5)
        }
    };

    difficultyProfileCache = { opponentKey: opponent.key, profile };
    currentDifficultyKey = opponent.difficultyKey;
    return profile;
}

function getCurrentOpponent() {
    const index = Phaser.Math.Clamp(Math.trunc(campaignState.opponentIndex) || 0, 0, OPPONENT_TEAMS.length - 1);
    return OPPONENT_TEAMS[index];
}

function applyCampaignState(nextState) {
    campaignState = {
        loggedIn: Boolean(nextState?.loggedIn ?? campaignState.loggedIn),
        teamName: String(nextState?.teamName || campaignState.teamName || 'Meu Time'),
        opponentIndex: Phaser.Math.Clamp(Number(nextState?.opponentIndex ?? campaignState.opponentIndex) || 0, 0, OPPONENT_TEAMS.length - 1),
        wins: Math.max(0, Number(nextState?.wins ?? campaignState.wins) || 0),
        losses: Math.max(0, Number(nextState?.losses ?? campaignState.losses) || 0),
        campaignCompleted: Boolean(nextState?.campaignCompleted ?? campaignState.campaignCompleted)
    };
    difficultyProfileCache = null;
    currentDifficultyKey = getCurrentOpponent().difficultyKey;
    renderCampaign();
}

function initCampaignSelector() {
    const modal = document.getElementById('campaign-modal');
    if (!modal) {
        difficultySelected = true;
        return;
    }

    if (!campaignState.loggedIn) restoreGuestCampaign();
    applyCampaignState(campaignState);

    const teamNameInput = document.getElementById('player-team-name');
    document.getElementById('save-team-name')?.addEventListener('click', saveTeamName);
    teamNameInput?.addEventListener('keydown', (event) => {
        event.stopPropagation();
        if (event.key === 'Enter') saveTeamName();
    });
    teamNameInput?.addEventListener('keyup', (event) => event.stopPropagation());
    teamNameInput?.addEventListener('focus', () => setGameKeyboardEnabled(false));
    teamNameInput?.addEventListener('blur', () => setGameKeyboardEnabled(true));
    document.getElementById('start-campaign-match')?.addEventListener('click', startCampaignMatch);
}

function setGameKeyboardEnabled(enabled) {
    if (gameScene?.input?.keyboard) gameScene.input.keyboard.enabled = enabled;
}

function renderCampaign() {
    const opponent = getCurrentOpponent();
    const teamInput = document.getElementById('player-team-name');
    const opponentList = document.getElementById('campaign-opponents');
    const startButton = document.getElementById('start-campaign-match');
    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = String(value);
    };

    if (teamInput && document.activeElement !== teamInput) teamInput.value = campaignState.teamName;
    setText('campaign-wins', campaignState.wins);
    setText('campaign-losses', campaignState.losses);
    setText('campaign-opponent-name', opponent.name);
    setText('campaign-difficulty', opponent.difficultyLabel);
    setText('player-team-label', campaignState.teamName);
    setText('opponent-team-label', opponent.name);

    const difficultyBadge = document.getElementById('campaign-difficulty');
    if (difficultyBadge) difficultyBadge.style.setProperty('--team-color', opponent.color);
    if (startButton) {
        startButton.textContent = campaignState.campaignCompleted
            ? `Jogar novamente contra ${opponent.name}`
            : `Jogar contra ${opponent.name}`;
    }

    if (opponentList) {
        opponentList.replaceChildren(...OPPONENT_TEAMS.map((team, index) => {
            const item = document.createElement('li');
            const reached = index < campaignState.opponentIndex || campaignState.campaignCompleted;
            const current = index === campaignState.opponentIndex && !campaignState.campaignCompleted;
            item.className = reached ? 'is-complete' : current ? 'is-current' : 'is-locked';
            item.innerHTML = `<i style="--team-color:${team.color}"></i><span>${team.name}</span><em>${team.difficultyLabel}</em>`;
            return item;
        }));
    }
}

function restoreGuestCampaign() {
    try {
        const saved = JSON.parse(localStorage.getItem('voleiGuestCampaign') || 'null');
        if (saved && typeof saved === 'object') campaignState = { ...campaignState, ...saved, loggedIn: false };
    } catch (_error) {}
}

function saveGuestCampaign() {
    try {
        localStorage.setItem('voleiGuestCampaign', JSON.stringify({
            teamName: campaignState.teamName,
            opponentIndex: campaignState.opponentIndex,
            wins: campaignState.wins,
            losses: campaignState.losses,
            campaignCompleted: campaignState.campaignCompleted
        }));
    } catch (_error) {}
}

async function postCampaign(action, payload = {}) {
    const response = await fetch('campaign.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, csrf: window.VOLEI_CAMPAIGN_CSRF || '', ...payload })
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.ok) throw new Error(result.error || `HTTP ${response.status}`);
    return result;
}

async function saveTeamName() {
    const input = document.getElementById('player-team-name');
    const button = document.getElementById('save-team-name');
    const feedback = document.getElementById('team-name-feedback');
    const teamName = String(input?.value || '').trim();
    if (teamName.length < 2 || teamName.length > 30) {
        if (feedback) feedback.textContent = 'Use um nome entre 2 e 30 caracteres.';
        return;
    }

    if (button) button.disabled = true;
    if (feedback) feedback.textContent = 'Salvando...';
    try {
        if (campaignState.loggedIn) {
            const result = await postCampaign('rename', { teamName });
            applyCampaignState(result.state);
            if (feedback) feedback.textContent = 'Nome salvo na sua conta.';
        } else {
            applyCampaignState({ ...campaignState, teamName });
            saveGuestCampaign();
            if (feedback) feedback.textContent = 'Nome salvo neste navegador.';
        }
    } catch (error) {
        if (feedback) feedback.textContent = error.message || 'Não foi possível salvar.';
    } finally {
        if (button) button.disabled = false;
    }
}

async function startCampaignMatch() {
    const button = document.getElementById('start-campaign-match');
    const feedback = document.getElementById('campaign-feedback');
    if (button) button.disabled = true;
    if (feedback) feedback.textContent = unsavedMatchResult ? 'Salvando o resultado anterior...' : 'Preparando partida...';

    try {
        if (unsavedMatchResult) await saveMatchResultToServer(unsavedMatchResult);

        if (campaignState.loggedIn) {
            const result = await postCampaign('start');
            activeMatchToken = result.matchToken;
            if (Number.isInteger(result.opponentIndex)) {
                applyCampaignState({ ...campaignState, opponentIndex: result.opponentIndex });
            }
        } else {
            activeMatchToken = `guest-${Date.now()}`;
        }

        difficultySelected = true;
        document.getElementById('campaign-modal')?.classList.add('is-hidden');
        document.getElementById('campaign-modal')?.setAttribute('aria-hidden', 'true');
        if (feedback) feedback.textContent = '';
        nextServer = 'PLAYER';
        resetMatchScore();
        if (gameScene) resetServe();
        updateAdminDebugLogModal();
    } catch (error) {
        if (feedback) feedback.textContent = error.message || 'Não foi possível iniciar a partida.';
    } finally {
        if (button) button.disabled = false;
    }
}

function openCampaignModal(message = '') {
    difficultySelected = false;
    renderCampaign();
    const modal = document.getElementById('campaign-modal');
    modal?.classList.remove('is-hidden');
    modal?.setAttribute('aria-hidden', 'false');
    const feedback = document.getElementById('campaign-feedback');
    if (feedback) feedback.textContent = message;
}

function preload() {
    this.load.on('loaderror', (file) => {
        const failedUrl = file.src || file.url || file.key;
        console.error(`Erro ao carregar asset: ${failedUrl}`);
        showAssetError(`Nao foi possivel carregar: ${failedUrl}`);
    });

    this.load.image('court', 'assets/quadra.png');
    this.load.image('ball', 'assets/ball.png');
    this.load.image('playerBack', 'assets/player-costas.png');
    this.load.image('playerLift', 'assets/player-levantar.png');
    this.load.image('playerMachete', 'assets/player-machete.png');
    this.load.image('playerHit', 'assets/player-bater.png');
    this.load.image('playerRight', 'assets/player-direita.png');
    this.load.image('playerLeft', 'assets/player-esquerda.png');
    this.load.image('playerWalk', 'assets/player-andando.png');
    this.load.image('oppParado', 'assets/player2-parado.png');
    this.load.image('oppMachete', 'assets/player2-machete.png');
    this.load.image('oppLevantador', 'assets/player2-levantador.png');
    this.load.image('oppAtaque', 'assets/player2-ataque.png');
    this.load.image('rede', 'assets/rede.png');
    this.load.image('shadow1', 'assets/sombra1.png');
    this.load.image('shadow2', 'assets/sombra2.png');
}

function showAssetError(message) {
    const panel = document.getElementById('debug-panel');
    if (!panel) return;
    panel.style.display = 'block';
    panel.textContent = `${panel.textContent ? `${panel.textContent}\n` : ''}${message}`;
}

function create() {
    gameScene = this;
    this.cameras.main.setBackgroundColor('#07111f');
    this.add.image(400, 300, 'court').setDisplaySize(800, 600).setDepth(0);

    createDevOverlay(this);
    createChargeBar(this);
    createStatusText(this);
    if (SHOW_TRAJECTORY) createTrajectoryOverlay(this);
    debugPanelEl = document.getElementById('debug-panel');

    // --- Formação Time Jogador (Cruz) ---
    // Receptor (controlável)
    playerBacker = this.physics.add.sprite(PLAYER_HOME.RECEPTOR.x, PLAYER_HOME.RECEPTOR.y, 'playerBack');
    playerBacker.setDisplaySize(PLAYER.size, PLAYER.size);
    playerBacker.setOrigin(0.5, 1);
    playerBacker.setCollideWorldBounds(false);
    playerBacker.setDepth(12);

    // Alias de compatibilidade: `player` aponta para o sprite controlado
    player = playerBacker;
    controlledPlayer = playerBacker;

    playerSetter = this.add.sprite(PLAYER_HOME.SETTER.x, PLAYER_HOME.SETTER.y, 'playerBack');
    playerSetter.setDisplaySize(PLAYER.size, PLAYER.size);
    playerSetter.setDepth(12);

    // Atacante esquerdo (controlável)
    playerLeft = this.physics.add.sprite(PLAYER_HOME.LEFT.x, PLAYER_HOME.LEFT.y, 'playerBack');
    playerLeft.setDisplaySize(PLAYER.size, PLAYER.size);
    playerLeft.setOrigin(0.5, 1);
    playerLeft.setCollideWorldBounds(false);
    playerLeft.setDepth(12);

    // Atacante direito (controlável)
    playerRight = this.physics.add.sprite(PLAYER_HOME.RIGHT.x, PLAYER_HOME.RIGHT.y, 'playerBack');
    playerRight.setDisplaySize(PLAYER.size, PLAYER.size);
    playerRight.setOrigin(0.5, 1);
    playerRight.setCollideWorldBounds(false);
    playerRight.setDepth(12);
    playerRight.setVisible(true);

    // Lista do time do jogador (3 controláveis)
    playerTeam = [playerBacker, playerLeft, playerRight];

    const netSprite = this.add.image(390, 240, 'rede');
    netSprite.setDepth(11);

    // --- Formação Time Adversário (Cruz) ---
    opponentBack = this.add.sprite(OPPONENT_AI.backHome.x, OPPONENT_AI.backHome.y, 'oppParado');
    opponentBack.setDisplaySize(PLAYER.size, PLAYER.size);
    opponentBack.setOrigin(0.5, 1);
    opponentBack.setDepth(7);

    opponentFront = this.add.sprite(OPPONENT_AI.setterHome.x, OPPONENT_AI.setterHome.y, 'oppParado');
    opponentFront.setDisplaySize(PLAYER.size, PLAYER.size);
    opponentFront.setOrigin(0.5, 1);
    opponentFront.setDepth(9);

    oppLeft = this.add.sprite(OPPONENT_AI.leftHome.x, OPPONENT_AI.leftHome.y, 'oppParado');
    oppLeft.setDisplaySize(PLAYER.size, PLAYER.size);
    oppLeft.setOrigin(0.5, 1);
    oppLeft.setDepth(8);

    oppRight = this.add.sprite(OPPONENT_AI.rightHome.x, OPPONENT_AI.rightHome.y, 'oppParado');
    oppRight.setDisplaySize(PLAYER.size, PLAYER.size);
    oppRight.setOrigin(0.5, 1);
    oppRight.setDepth(8);

    // Sprite de ataque (pode ser o da direita ou esquerda conforme IA)
    opponentAttacker = oppLeft; 

    // Mantem apenas os raios dos jogadores visiveis; quadrantes vermelhos ficam ocultos.
    createPlayerRadiusOverlay(this);

    ballShadow = this.add.ellipse(0, 0, 28, 10, 0x000000, 0.28);
    ballShadow.setVisible(false);
    ballShadow.setDepth(6);

    landingShadow = this.add.image(0, 0, 'shadow1');
    landingShadow.setVisible(false);
    landingShadow.setDepth(5);

    ballBody = this.physics.add.image(COURT.playerStartX, COURT.playerStartY, 'ball');
    ballBody.setVisible(false);
    ballBody.setCircle(16);
    ballBody.setCollideWorldBounds(false);
    ballBody.setDamping(true);
    ballBody.setDrag(0.92, 0.92);
    ballBody.setMaxVelocity(900, 900);

    ball = this.add.image(0, 0, 'ball');
    ball.setDisplaySize(BALL.size, BALL.size);
    ball.setVisible(false);
    ball.setDepth(15);

    trail = this.add.particles(0, 0, 'ball', {
        speed: 0,
        scale: { start: 0.45, end: 0 },
        alpha: { start: 0.35, end: 0 },
        lifespan: 170,
        frequency: 18,
        emitting: false
    });
    trail.setDepth(14);
    trail.startFollow(ball);

    cursors = this.input.keyboard.createCursorKeys();
    keys = this.input.keyboard.addKeys({
        L: Phaser.Input.Keyboard.KeyCodes.L,
        A: Phaser.Input.Keyboard.KeyCodes.A,
        S: Phaser.Input.Keyboard.KeyCodes.S,
        D: Phaser.Input.Keyboard.KeyCodes.D,
        Q: Phaser.Input.Keyboard.KeyCodes.Q,
        W: Phaser.Input.Keyboard.KeyCodes.W,
        E: Phaser.Input.Keyboard.KeyCodes.E,
        CEDILLA: Phaser.Input.Keyboard.KeyCodes.SEMICOLON || 186,
        SPACE: Phaser.Input.Keyboard.KeyCodes.SPACE
    });
    setGameKeyboardEnabled(document.activeElement?.id !== 'player-team-name');

    // Posições "fixas" da formação do time do jogador (para alternância consistente)
    homePos = {
        RECEPTOR: { ...PLAYER_HOME.RECEPTOR },
        LEFT: { ...PLAYER_HOME.LEFT },
        RIGHT: { ...PLAYER_HOME.RIGHT }
    };

    // Começa controlando o receptor
    setControlledRole('RECEPTOR');
    resetServe();
    createLiftChoiceUi(this);
    createTouchControls(this);

    prevBodyX = ballBody.x;
    prevBodyY = ballBody.y;
    prevBallZ = ballZ;
    prevBallVZ = ballVZ;
    smoothedLandingX = ballBody.x;
    smoothedLandingY = ballBody.y;
}

function update(_time, delta) {
    if (!difficultySelected) {
        updateDevOverlay(this);
        updateDebugPanel(this);
        return;
    }

    handlePlayerRoleSwitch();
    handlePlayerMovement(delta);
    handleServeInput(this);
    updateBallPhysics(this);
    if (SHOW_TRAJECTORY) updateTrajectoryOverlay(this);
    updateLiftChoiceUi(this);
    clampPlayerTeam();
    updatePlayerRadiusOverlay();
    
    // Atualização visual da altura do jogador (pulo)
    // Ajusta a origem vertical para fazer o sprite subir sem alterar a posição Y física
    getControlledPlayer().setDisplayOrigin(getControlledPlayer().width * 0.5, getControlledPlayer().height * 1.0 + (playerZ * 0.8));
    
    updateDevOverlay(this);
    updateDebugPanel(this);
}

function updateDebugPanel(scene) {
    if (!debugPanelEl) return;
    if (!SHOW_DEBUG) {
        debugPanelEl.style.display = 'none';
        return;
    }
    debugPanelEl.style.display = 'block';

    const landing = lastLandingMark
        ? `landing: x=${lastLandingMark.width} y=${lastLandingMark.depth}`
        : `landing: ---`;
    const plan = lastOppAttackPlan
        ? [
            `oppAttackPlan @${Math.round(lastOppAttackPlan.t)}`,
            `from: x=${Math.round(lastOppAttackPlan.fromX)} y=${Math.round(lastOppAttackPlan.fromY)} (depth ${Math.round(lastOppAttackPlan.fromY - NET.y)})`,
            `to:   x=${Math.round(lastOppAttackPlan.toX)} y=${Math.round(lastOppAttackPlan.toY)} (depth ${Math.round(lastOppAttackPlan.toY - NET.y)})`,
            `flightTime: ${lastOppAttackPlan.flightTime.toFixed(2)}s`,
            `vel: vx=${Math.round(lastOppAttackPlan.vx)} vy=${Math.round(lastOppAttackPlan.vy)}`,
            `z: ballZ=${Math.round(lastOppAttackPlan.ballZ)} ballVZ=${Math.round(lastOppAttackPlan.ballVZ)} gravity=${Math.round(lastOppAttackPlan.gravityZ)}`
        ].join('\n')
        : 'oppAttackPlan: ---';

    debugPanelEl.textContent = [
        `difficulty: ${getDifficultyProfile().label}`,
        `gamePhase: ${gamePhase}`,
        `state: ${state}`,
        `lastTouch: ${lastTouch}`,
        landing,
        '',
        plan
    ].join('\n');
}

function clampPlayerTeam() {
    // Evita drift/acúmulo de velocidade fora da quadra em sprites não controlados.
    if (!playerTeam || playerTeam.length === 0) return;
    playerTeam.forEach((p) => {
        if (!p) return;
        const maxY = getPlayerMaxY(p);
        p.x = Phaser.Math.Clamp(p.x, COURT.minX, COURT.maxX);
        p.y = Phaser.Math.Clamp(p.y, COURT.minY, maxY);
        if (p.body) {
            p.body.velocity.x = Phaser.Math.Clamp(p.body.velocity.x, -PHYS_CLAMP.maxVX, PHYS_CLAMP.maxVX);
            p.body.velocity.y = Phaser.Math.Clamp(p.body.velocity.y, -PHYS_CLAMP.maxVY, PHYS_CLAMP.maxVY);
        }
    });
}

function getPlayerMaxY(sprite) {
    if (sprite === playerBacker && gamePhase === 'SERVE' && nextServer === 'PLAYER') {
        return PLAYER_HOME.SERVER.y;
    }
    return COURT.maxY;
}

function handlePlayerRoleSwitch() {
    // Troca de controle por turno:
    // Q = atacante esquerdo, E = atacante direito.
    // Durante o ataque, só alterna entre Q/E (atacantes).
    // Permite alternar já no saque (state READY). Durante o rali, só permite alternar
    // enquanto a bola estiver do lado do jogador.
    // (removido) não bloqueia alternância quando a bola está do outro lado

    const inAttackPhase = state === 'TOSS' || state === 'HIT_WINDOW' || state === 'FLYING' || state === 'NET_HIT' || state === 'BOUNCE_ROLL';

    // No status de saque, trava alternância: só receptor pode ser controlado.
    if (gamePhase === 'SERVE') {
        if (controlledRole !== 'RECEPTOR') setControlledRole('RECEPTOR');
        return;
    }

    if (Phaser.Input.Keyboard.JustDown(keys.Q)) {
        // Escolha do lado do levantamento é feita pelas setas (UI), não por Q/E.
        // Só troca o controle para atacante durante o rali (bola vindo/levantada), não no READY de saque
        setControlledRole('LEFT');
    } else if (Phaser.Input.Keyboard.JustDown(keys.E)) {
        // Escolha do lado do levantamento é feita pelas setas (UI), não por Q/E.
        setControlledRole('RIGHT');
    }
}

function setControlledRole(role) {
    if (controlledRole === role) return;

    // Agora existem 3 sprites físicos; alternância troca apenas qual recebe input.
    if (homePos) {
        resetPlayerFormation(gamePhase === 'SERVE' && nextServer === 'PLAYER');
    }

    if (role === 'RECEPTOR') controlledPlayer = playerBacker;
    else if (role === 'LEFT') controlledPlayer = playerLeft;
    else if (role === 'RIGHT') controlledPlayer = playerRight;

    // `player` sempre referencia o sprite controlado para reaproveitar lógica existente
    player = controlledPlayer;
    if (player.texture && player.texture.key === 'playerHit') {
        player.setTexture('playerBack');
        playerHitPoseUntil = 0;
    }
    // Zera velocidades para evitar "drift" do antigo controlado
    if (playerTeam && playerTeam.length) {
        playerTeam.forEach((p) => p?.body?.setVelocity?.(0));
    }

    controlledRole = role;
}

function getControlledPlayer() {
    return controlledPlayer || playerBacker;
}

function handlePlayerMovement(delta) {
    const p = getControlledPlayer();
    p.setVelocity(0);

    const isServing = state === 'READY' || state === 'CHARGING_LIFT' || state === 'TOSS' || state === 'HIT_WINDOW';
    const moveStep = PLAYER.speedX * (delta / 1000);
    const moveStepY = (isServing ? PLAYER.serveSpeedY : PLAYER.speedY) * (delta / 1000);
    const prevX = p.x;
    const prevY = p.y;

    if (isServing) {
        if (isInputDown('left')) p.x -= moveStep;
        else if (isInputDown('right')) p.x += moveStep;

        // No saque, trava movimento vertical do controlado
        if (gamePhase !== 'SERVE') {
            if (isInputDown('up')) p.y -= moveStepY;
            else if (isInputDown('down')) p.y += moveStepY;
        }
    } else {
        if (isInputDown('left')) p.setVelocityX(-PLAYER.speedX);
        else if (isInputDown('right')) p.setVelocityX(PLAYER.speedX);

        if (isInputDown('up')) p.setVelocityY(-PLAYER.speedY);
        else if (isInputDown('down')) p.setVelocityY(PLAYER.speedY);
    }

    p.x = Phaser.Math.Clamp(p.x, COURT.minX, COURT.maxX);
    p.y = Phaser.Math.Clamp(p.y, COURT.minY, getPlayerMaxY(p));

    updatePlayerSprite(p, delta, prevX, prevY);

    if (state === 'READY') {
        placeBallAboveHead(false);
    }
}

function updatePlayerSprite(p, delta, prevX, prevY) {
    // Don't override explicit poses (lift/hit) or while charging/hitting
    if (state === 'CHARGING_LIFT' || state === 'TOSS' || state === 'HIT_WINDOW' || state === 'FLYING' || state === 'NET_HIT') {
        return;
    }
    const now = p.scene?.time?.now ?? 0;
    if (p.texture && p.texture.key === 'playerHit' && now < playerHitPoseUntil) {
        return;
    }
    if (p.texture && p.texture.key === 'playerHit' && now >= playerHitPoseUntil) {
        p.setTexture('playerBack');
    }
    if (p.texture && p.texture.key === 'playerMachete' && (keys?.CEDILLA?.isDown || virtualInput.receiveDown)) {
        return;
    }

    const dx = p.x - prevX;
    const dy = p.y - prevY;
    const moving = Math.abs(dx) > 0.05 || Math.abs(dy) > 0.05;

    if (!moving) {
        stepTimerMs = 0;
        stepToggle = false;
        p.setTexture('playerBack');
        return;
    }

    // Prefer horizontal facing when moving left/right
    if (Math.abs(dx) >= Math.abs(dy)) {
        p.setTexture(dx >= 0 ? 'playerRight' : 'playerLeft');
        stepTimerMs = 0;
        stepToggle = false;
        return;
    }

    // Forward/back movement uses a simple step simulation: alternate between standing and walk
    stepTimerMs += delta;
    if (stepTimerMs >= 140) {
        stepTimerMs = 0;
        stepToggle = !stepToggle;
    }
    p.setTexture(stepToggle ? 'playerWalk' : 'playerBack');
}

function handleServeInput(scene) {
    // Status de saque: só o receptor pode iniciar levantamento/saque.
    if (gamePhase === 'SERVE' && controlledRole !== 'RECEPTOR') {
        // Permite mover/posicionar, mas não iniciar o saque/levantamento.
        if (consumeInputPressed('lift', keys.L) || consumeInputReleased('lift', keys.L)) {
            return;
        }
    }

    // Hold L to charge lift height, release to toss.
    if (state === 'READY' && nextServer === 'PLAYER' && consumeInputPressed('lift', keys.L)) {
        clearTrajectoryOverlay();
        placeBallAboveHead(true);
        state = 'CHARGING_LIFT';
        liftChargeStart = scene.time.now;
        getControlledPlayer().setTexture('playerLift');
        return;
    }

    if (state === 'CHARGING_LIFT') {
        let chargeMs = scene.time.now - liftChargeStart;
        const reachedMax = chargeMs >= LIFT_CHARGE.maxMs;

        if (consumeInputReleased('lift', keys.L) || reachedMax) {
            const finalMs = Math.min(chargeMs, LIFT_CHARGE.maxMs);
            hitPowerRatio = finalMs / LIFT_CHARGE.maxMs; // Salva a força para o hit

            // Levantamento agora é SEMPRE na altura fixa de 125
            const targetZ = 125;

            state = 'TOSS';
            queuedServeHitAt = 0;
            ballZ = 0;
            ballVZ = Math.sqrt(2 * Math.abs(BALL.gravityZToss) * targetZ) * LIFT_CHARGE.vzScale;
            liftChargeStart = 0;

            scene.time.delayedCall(160, () => {
                if (state === 'TOSS' || state === 'HIT_WINDOW') getControlledPlayer().setTexture('playerBack');
            });
        }
        return;
    }

    if (state === 'TOSS' && ballVZ < 0 && ballZ <= BALL.hitWindowZ) {
        state = 'HIT_WINDOW';
        ball.setTint(0xff2626);

        const queuedAge = scene.time.now - queuedServeHitAt;
        if (queuedServeHitAt > 0 && queuedAge <= 180 && (isJumping || playerZ > 8)) {
            queuedServeHitAt = 0;
            const q = getAttackQuality(ballZ, BALL.hitWindowZ);
            performServeHitInstant(scene, q);
            return;
        }
        queuedServeHitAt = 0;
    }

    // Pulo do Jogador
    if (consumeInputPressed('jump', keys.SPACE) && !isJumping) {
        startPlayerJump(scene);
    }

    // Acao unificada: no chao = manchete; no ar = ataque/saque.
    if (consumeActionPressed()) {
        handleContextAction(scene);
    }

    if (state === 'HIT_WINDOW' && ballZ <= 0 && ballVZ <= 0) {
        awardPoint(scene, false);
    }
}

function isInputDown(action) {
    const keyMap = {
        left: [cursors?.left, keys?.A],
        right: [cursors?.right, keys?.D],
        up: [cursors?.up, keys?.W],
        down: [cursors?.down, keys?.S]
    };
    return Boolean(keyMap[action]?.some((key) => key?.isDown) || virtualInput[action]);
}

function consumeInputPressed(action, key) {
    const prop = `${action}Pressed`;
    const pressed = Boolean((key && Phaser.Input.Keyboard.JustDown(key)) || virtualInput[prop]);
    virtualInput[prop] = false;
    return pressed;
}

function consumeInputReleased(action, key) {
    const prop = `${action}Released`;
    const released = Boolean((key && Phaser.Input.Keyboard.JustUp(key)) || virtualInput[prop]);
    virtualInput[prop] = false;
    return released;
}

function consumeActionPressed() {
    const pressed = Boolean(
        (keys?.CEDILLA && Phaser.Input.Keyboard.JustDown(keys.CEDILLA)) ||
        virtualInput.actionPressed
    );
    virtualInput.actionPressed = false;
    return pressed;
}

function startPlayerJump(scene) {
    const jumper = getControlledPlayer();
    isJumping = true;
    scene.tweens.add({
        targets: { z: 0 },
        z: 130,
        duration: 460,
        yoyo: true,
        ease: 'Power1.easeOut',
        onUpdate: (tween) => {
            playerZ = tween.getValue();
        },
        onComplete: () => {
            isJumping = false;
            playerZ = 0;
            if (jumper.texture && jumper.texture.key === 'playerHit') {
                jumper.setTexture('playerBack');
                playerHitPoseUntil = 0;
            }
        }
    });
}

function handleContextAction(scene) {
    if (isJumping || playerZ > 8) {
        handleAirAttack(scene);
        return;
    }

    handleGroundReceive(scene);
}

function handleAirAttack(scene) {
    const p = getControlledPlayer();
    const distToBall = Phaser.Math.Distance.Between(p.x, p.y, ballBody.x, ballBody.y);

    playerHitPoseUntil = scene.time.now + 350;
    p.setTexture('playerHit');
    scene.time.delayedCall(350, () => {
        if (p.texture && p.texture.key === 'playerHit' && !isJumping) {
            p.setTexture('playerBack');
            playerHitPoseUntil = 0;
        }
    });

    if (state === 'HIT_WINDOW') {
        const q = getAttackQuality(ballZ, BALL.hitWindowZ);
        performServeHitInstant(scene, q);
        return;
    }

    if (state === 'TOSS') {
        const canQueueServe = ballVZ < 0 && ballZ <= BALL.hitWindowZ + 24 && (isJumping || playerZ > 8);
        queuedServeHitAt = canQueueServe ? scene.time.now : 0;
        showStatusMessage(scene, canQueueServe ? 'SAQUE ARMADO' : 'PULA PARA SACAR', 650);
        return;
    }

    if (distToBall < 60 && ballZ > 40) {
        performRallyHit(scene);
    }
}

function handleGroundReceive(scene) {
    const tex = scene.textures.exists('playerMachete') ? 'playerMachete' : 'playerBack';
    const receiver = getControlledPlayer();
    receiver.setTexture(tex);
    scene.time.delayedCall(500, () => {
        // Não sobrescreve poses de lift/hit
        if (state !== 'CHARGING_LIFT' && state !== 'TOSS' && state !== 'HIT_WINDOW') {
            receiver.setTexture('playerBack');
        }
    });
}

function performPlayerFreeball(scene, receiver) {
    state = 'FLYING';
    gamePhase = 'RALLY';
    lastTouch = 'PLAYER_HIT';
    lastPlayerShotKind = 'ATTACK';
    lastPlayerAttackPower = 0.02;
    opponentReceiveAttemptedForPlayerHit = false;
    opponentDefenseStartedAtMs = 0;
    opponentLowServeDecision = null;
    resetOpponentLetOutDecision();

    ballBody.x = receiver.x;
    ballBody.y = receiver.y - 18;
    ballZ = Math.max(ballZ, 55);
    ballVZ = 190;

    const flightTime = 1.85;
    const targetX = Phaser.Math.Clamp(receiver.x + Phaser.Math.Between(-70, 70), COURT.minX + 70, COURT.maxX - 70);
    const targetY = NET.y - Phaser.Math.Between(105, 165);
    const vx = (targetX - ballBody.x) / flightTime;
    const vy = (targetY - ballBody.y) / flightTime;
    ballBody.setVelocity(
        Phaser.Math.Clamp(vx, -PHYS_CLAMP.maxVX, PHYS_CLAMP.maxVX),
        Phaser.Math.Clamp(vy, -PHYS_CLAMP.maxVY, PHYS_CLAMP.maxVY)
    );

    beginShotLog('MANCHETE 3 TOQUE', 'JOGADOR', {
        force: lastPlayerAttackPower.toFixed(2),
        quality: controlledRole
    });
    setShotCalculations({
        'modelo': 'bola de graca lenta no terceiro toque',
        'origem': `x ${Math.round(ballBody.x)} y ${Math.round(ballBody.y)} z ${Math.round(ballZ)}`,
        'alvo': `x ${Math.round(targetX)} y ${Math.round(targetY)}`,
        'tempo voo': `${flightTime.toFixed(3)}s`,
        'velocidade aplicada': `vx ${Math.round(ballBody.body.velocity.x)} vy ${Math.round(ballBody.body.velocity.y)} vz ${Math.round(ballVZ)}`
    });
    setShotVelocity(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ);

    playerHitPoseUntil = scene.time.now + 420;
    scene.time.delayedCall(520, () => {
        if (receiver.texture && receiver.texture.key === 'playerMachete') {
            receiver.setTexture('playerBack');
        }
        playerHitPoseUntil = 0;
        if (state === 'FLYING' && lastTouch === 'PLAYER_HIT') {
            setControlledRole('RECEPTOR');
        }
    });

    trail.start();
    scene.cameras.main.shake(80, 0.003);
    addHitEffect(scene, { shake: 0.002 });
}

function updateBallPhysics(scene) {
    if (state === 'READY' || state === 'POINT' || state === 'OPP_SERVE_WAIT') return;

    const dt = scene.game.loop.delta / 1000;
    const dtClamped = Phaser.Math.Clamp(dt, 1 / 240, 1 / 20);

    // Physics integration: Z axis manual handling
    const az = getGravityZForState();
    ballZ += ballVZ * dtClamped;
    ballVZ += az * dtClamped;
    ballVZ = Phaser.Math.Clamp(ballVZ, PHYS_CLAMP.minVZ, PHYS_CLAMP.maxVZ);
    debugShot.maxZ = Math.max(debugShot.maxZ, ballZ);

    updateLandingShadow(scene);

    // Hard bounds: if the ball leaves the visible/allowed court area, end and reset.
    if (state === 'FLYING' || state === 'BOUNCE_ROLL') {
        const margin = 40;
        const outTopY = -250;
        const crossedTopLimit = prevBodyY > outTopY && ballBody.y <= outTopY;
        const out =
            ballBody.x < COURT.minX - margin ||
            ballBody.x > COURT.maxX + margin ||
            crossedTopLimit ||
            ballBody.y <= outTopY ||
            ballBody.y > COURT.height + margin;
        if (out) {
            landingShadowState = 0;
            landingShadow.setVisible(false);
            ball.setVisible(false);
            ballShadow.setVisible(false);
            trail.stop();
            const lastTouchWasOpponent = lastTouch === 'OPP_HIT' || lastTouch === 'OPP_PASS' || lastTouch === 'OPP_SET';
            finishShotLog('fora da quadra');
            awardPoint(scene, lastTouchWasOpponent);
            return;
        }
    }

    // Robust net collision: detect crossing NET.y between frames and evaluate Z at crossing instant.
    if (state === 'FLYING') {
        const y0 = prevBodyY;
        const y1 = ballBody.y;
        const crossesNet = (y0 - NET.y) * (y1 - NET.y) <= 0;
        const vy = ballBody.body.velocity.y;
        updateClosestNetCrossing(scene);
        if (!hasRecordedNetCrossing && crossesNet) {
            const denom = y1 - y0;
            if (Math.abs(denom) > 1e-6) {
                const u = Phaser.Math.Clamp((NET.y - y0) / denom, 0, 1);
                const tCross = u * dtClamped;
                const zAtNet = prevBallZ + prevBallVZ * tCross + 0.5 * az * tCross * tCross;
                const xAtNet = Phaser.Math.Linear(prevBodyX, ballBody.x, u);
                addNetHeightMarker(scene, xAtNet, zAtNet, zAtNet >= NET.heightZ);
                hasRecordedNetCrossing = true;
            }
        }
        if (crossesNet && isBallMovingBetweenSides()) {
            const denom = y1 - y0;
            if (Math.abs(denom) > 1e-6) {
                const u = Phaser.Math.Clamp((NET.y - y0) / denom, 0, 1);
                const tCross = u * dtClamped;
                const zAtNet = prevBallZ + prevBallVZ * tCross + 0.5 * az * tCross * tCross;
                const xAtNet = Phaser.Math.Linear(prevBodyX, ballBody.x, u);
                if (zAtNet < NET.heightZ) {
                    handleNetFault(scene);
                    return;
                }
            }
        }

        // --- IA adversária: recepção -> levantamento -> ataque ---
        const bolaVindoParaAdversario = ballBody.body.velocity.y < 0;
        const ballOnOpponentSide = ballBody.y <= NET.y + 12;
        const canOpponentPlayBall =
            ballOnOpponentSide &&
            lastTouch !== 'OPP_HIT' &&
            (bolaVindoParaAdversario || lastTouch === 'OPP_PASS' || lastTouch === 'OPP_SET');
        const firstOpponentTouch = canOpponentPlayBall && bolaVindoParaAdversario && lastTouch !== 'OPP_PASS' && lastTouch !== 'OPP_SET';
        if (firstOpponentTouch && opponentDefenseStartedAtMs === 0) {
            opponentDefenseStartedAtMs = scene.time.now;
        }
        const difficulty = getDifficultyProfile();
        const opponentReactionReady = !firstOpponentTouch ||
            scene.time.now - opponentDefenseStartedAtMs >= difficulty.reactionDelayMs;
        if (firstOpponentTouch && opponentReactionReady && canOpponentSetterTakeLowServe()) {
            opponentSetterTakeLowServe(scene);
            return;
        }

        const opponentShouldLetOut = firstOpponentTouch && shouldOpponentLetPlayerShotOut();
        const activeReceiver = firstOpponentTouch && opponentReactionReady && !opponentShouldLetOut
            ? getOpponentReceiverForBall()
            : null;
        if (firstOpponentTouch) {
            const receiverTrackRate = lastPlayerShotKind === 'SERVE'
                ? difficulty.trackRateServe
                : difficulty.trackRateAttack;
            moveOrReturnOpponentReceiver(opponentBack, OPPONENT_AI.backHome, activeReceiver === opponentBack, receiverTrackRate, dtClamped);
            moveOrReturnOpponentReceiver(oppLeft, OPPONENT_AI.leftHome, activeReceiver === oppLeft, receiverTrackRate, dtClamped);
            moveOrReturnOpponentReceiver(oppRight, OPPONENT_AI.rightHome, activeReceiver === oppRight, receiverTrackRate, dtClamped);
        } else {
            returnOpponentToHome(opponentBack, OPPONENT_AI.backHome, 4, dtClamped);
            returnOpponentToHome(oppLeft, OPPONENT_AI.leftHome, 4, dtClamped);
            returnOpponentToHome(oppRight, OPPONENT_AI.rightHome, 4, dtClamped);
        }

        const receivers = [
            { sprite: opponentBack, home: OPPONENT_AI.backHome },
            { sprite: oppLeft, home: OPPONENT_AI.leftHome },
            { sprite: oppRight, home: OPPONENT_AI.rightHome }
        ];
        if (opponentShouldLetOut) {
            markOpponentLetOutPrediction();
        } else {
            for (const item of receivers) {
                if (firstOpponentTouch && activeReceiver === item.sprite && canOpponentReceive(item.sprite, item.home)) {
                    if (!tryOpponentReceivePlayerAttack(scene, item.sprite)) return;
                    opponentPassToSetter(scene, item.sprite);
                    return;
                }
            }
        }

        const dSetter = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, opponentFront.x, opponentFront.y);
        if (canOpponentPlayBall && !opponentPassToSetterEvent && lastTouch === 'OPP_PASS' && dSetter < OPPONENT_AI.setRadius && ballZ < 135 && ballZ > 10) {
            opponentSetToAttacker(scene);
            return;
        }

        const dAttack = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, opponentAttacker.x, opponentAttacker.y);
        if (canOpponentPlayBall && !opponentSetAttackEvent && lastTouch === 'OPP_SET' && dAttack < OPPONENT_AI.attackRadius && ballZ < 150 && ballZ > 25) {
            performOpponentAttack(scene, opponentAttacker);
            return;
        }

        // Levantador do player: pega o passe da manchete independentemente da direcao da bola.
        // O passe sai do fundo para a rede (velocity.y negativo), entao nao pode ficar preso
        // ao bloco de "bola vindo para jogador".
        const dPlayerSetFromPass = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, playerSetter.x, playerSetter.y);
        if (dPlayerSetFromPass < 80 && lastTouch === 'PLAYER_PASS') {
            lastTouch = 'PLAYER_SET';
            ballBody.x = playerSetter.x;
            ballBody.y = playerSetter.y;
            const attackRole = chooseRandomAttackRole();
            desiredAttackRole = attackRole;

            ballVZ = 320;
            const flightTime = 1.45;
            const targetAttackX = attackRole === 'LEFT' ? homePos.LEFT.x : homePos.RIGHT.x;
            // Linha fixa de levantamento para ataque: profundidade 50 no lado do player.
            const targetAttackY = NET.y + 50;
            const vx = (targetAttackX - ballBody.x) / flightTime;
            const vvy = (targetAttackY - ballBody.y) / flightTime;
            ballBody.setVelocity(vx, vvy);

            scene.time.delayedCall(160, () => setControlledRole(attackRole));

            const tex = scene.textures.exists('playerLift') ? 'playerLift' : 'playerBack';
            playerSetter.setTexture(tex);
            scene.time.delayedCall(800, () => playerSetter.setTexture('playerBack'));
            scene.cameras.main.shake(100, 0.005);
            addHitEffect(scene, { shake: 0.003 });
        }

        // --- Rali do Time do Jogador ---
        const bolaVindoParaJogador = ballBody.body.velocity.y > 0;
        if (bolaVindoParaJogador) { 
            // IA do Defensor: Segue a bola apenas quando ela cruza a rede ou está perto
            if (false && ballBody.y > (NET.y - 20) && ballBody.y < playerBacker.y) {
                const trackSpeedX = 0.15;
                const trackSpeedY = 0.1;
                playerBacker.x += (ballBody.x - playerBacker.x) * trackSpeedX;
                playerBacker.y += (ballBody.y - playerBacker.y) * trackSpeedY;
            }

            // Limites de Movimentação (Não deixa ele invadir a outra quadra)
            playerBacker.x = Phaser.Math.Clamp(playerBacker.x, 100, 700);
            playerBacker.y = Phaser.Math.Clamp(playerBacker.y, 350, 560);

            // Colisão com Defensor (Fundo)
            const dPlayerBack = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, playerBacker.x, playerBacker.y);
            if (false && dPlayerBack < 45 && ballZ < 115 && ballZ > 10) {
                ballVZ = 380;
                const flightTime = 1.72;
                const vx = (playerSetter.x - ballBody.x) / flightTime;
                const vvy = (playerSetter.y - ballBody.y) / flightTime;
                ballBody.setVelocity(vx, vvy);
                
                const tex = scene.textures.exists('playerMachete') ? 'playerMachete' : 'oppMachete';
                playerBacker.setTexture(tex);
                scene.time.delayedCall(800, () => playerBacker.setTexture('playerBack'));
                scene.cameras.main.shake(100, 0.005);
                addHitEffect(scene, { shake: 0.003 });
            }

            // Colisão com o controlado (sprite físico `player`) - só recebe se estiver na pose de manchete.
            const p = getControlledPlayer();
            const dControlled = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, p.x, p.y);
            const receiving = keys?.CEDILLA?.isDown || virtualInput.receiveDown || (p.texture && p.texture.key === 'playerMachete');
            if (dControlled < RECEIVE.radius && ballZ < RECEIVE.maxZ && ballZ > RECEIVE.minZ && receiving) {
                // Evita múltiplos "toques" no mesmo contato
                if (scene.time.now - lastPlayerPassMs < 280) {
                    // ignore
                } else {
                    lastPlayerPassMs = scene.time.now;
                    if (lastTouch === 'PLAYER_SET') {
                        performPlayerFreeball(scene, p);
                        return;
                    }

                    lastTouch = 'PLAYER_PASS';
                    lastContactMark = { x: ballBody.x, y: ballBody.y, t: scene.time.now };
                    // Força a bola a ir para o levantador (sem "quicar" no chão)
                    state = 'FLYING';
                    ballBody.setVelocity(0);
                    ballZ = Math.max(ballZ, 24);
                    ballVZ = 285;
                    const flightTime = 1.25;
                    const vx = (playerSetter.x - ballBody.x) / flightTime;
                    const vvy = (playerSetter.y - ballBody.y) / flightTime;
                    ballBody.setVelocity(vx, vvy);

                    scene.time.delayedCall(450, () => {
                        if (state !== 'CHARGING_LIFT' && state !== 'TOSS' && state !== 'HIT_WINDOW') {
                            p.setTexture('playerBack');
                        }
                    });
                    scene.cameras.main.shake(100, 0.005);
                    addHitEffect(scene, { shake: 0.003 });
                }
            }

            // Colisão com Levantador (Frente)
            const dPlayerSet = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, playerSetter.x, playerSetter.y);
            if (false && dPlayerSet < 70 && ballZ < 150 && ballZ > 10 && lastTouch === 'PLAYER_PASS') {
                lastTouch = 'PLAYER_SET';
                ballVZ = 450; // Levantamento bem alto
                const flightTime = 2.0;
                const attackRole = chooseRandomAttackRole();
                desiredAttackRole = attackRole;
                const targetAttackX = attackRole === 'LEFT' ? 200 : 600;
                const targetAttackY = 420;
                const vx = (targetAttackX - ballBody.x) / flightTime;
                const vvy = (targetAttackY - ballBody.y) / flightTime;
                ballBody.setVelocity(vx, vvy);
                
                // Muda controle automaticamente para o atacante que vai receber o levantamento
                scene.time.delayedCall(160, () => setControlledRole(attackRole));
                
                const tex = scene.textures.exists('playerLift') ? 'playerLift' : 'playerBack';
                playerSetter.setTexture(tex);
                scene.time.delayedCall(800, () => playerSetter.setTexture('playerBack'));
                scene.cameras.main.shake(100, 0.005);
                addHitEffect(scene, { shake: 0.003 });
            }
        }
    }

    if (ballZ <= 0) {
        ballZ = 0;
        ballVZ = 0;

        if (state === 'FLYING') {
            lastLandingMark = {
                x: ballBody.x,
                y: ballBody.y,
                width: screenXToWidth(ballBody.x),
                depth: screenYToDepth(ballBody.y),
                t: scene.time.now
            };
            debugShot.landing = {
                x: screenXToWidth(ballBody.x),
                y: screenYToDepth(ballBody.y),
                z: 0
            };
            // One bounce, then roll to the back to sell the "quicando" feel.
            landingShadowState = 2;
            landingShadow.setTexture('shadow2');
            scene.cameras.main.shake(80, 0.006);
            recordShotLanding();
            if (isNetFaultByClosestCrossing()) {
                finishShotLog('bateu na rede');
                awardPoint(scene, getPointWinnerAfterLastTouchFault(), 'net');
            } else if (isPlayerAttackOutOnOpponentCourt()) {
                finishShotLog('fora da quadra');
                awardPoint(scene, false, 'out');
            } else if (isOpponentAttackOutOnPlayerCourt()) {
                finishShotLog('fora da quadra');
                awardPoint(scene, true, 'out');
            } else {
                finishShotLog('tocou no chao');
                finishRally(scene);
            }
            return;
        }

        if (state === 'BOUNCE_ROLL') {
            // Roll out to the back and end the rally once it reaches the back zone
            const reachedBack = rollDirY < 0 ? ballBody.y <= rollEndY : ballBody.y >= rollEndY;
            if (reachedBack) {
                finishRally(scene);
                return;
            }
            ballBody.setVelocity(ballBody.body.velocity.x * 0.92, rollDirY * 160);
            return;
        }
    }

    if (state === 'CHARGING_LIFT' || state === 'TOSS' || state === 'HIT_WINDOW') {
        const targetX = player.x;
        const targetY = getHeadBallGroundY();
        ballBody.setVelocity(0);
        ballBody.x += (targetX - ballBody.x) * 0.35;
        ballBody.y += (targetY - ballBody.y) * 0.35;
    }

    projectBall();

    // Save state for next frame's robust collision/physics bookkeeping
    prevBodyX = ballBody.x;
    prevBodyY = ballBody.y;
    prevBallZ = ballZ;
    prevBallVZ = ballVZ;
}

function canChooseLiftTarget() {
    if (!ballBody || !playerSetter || !ballBody.body) return false;
    if (gamePhase !== 'RALLY') return false;
    if (lastTouch !== 'PLAYER_PASS') return false;
    const ballOnPlayerSide = ballBody.y >= NET.y;
    if (!ballOnPlayerSide) return false;
    const vy = ballBody.body.velocity.y;
    // bola vindo para frente (em direção ao levantador)
    if (vy >= -10) return false;
    const depth = ballBody.y - NET.y;
    // janela aproximada do passe/levantamento
    return depth > 10 && depth < 160;
}

function handleLiftTargetSelection() {
    if (!canChooseLiftTarget()) return;
    if (Phaser.Input.Keyboard.JustDown(cursors.left)) desiredAttackRole = 'LEFT';
    if (Phaser.Input.Keyboard.JustDown(cursors.right)) desiredAttackRole = 'RIGHT';
}

function chooseRandomAttackRole() {
    return Phaser.Math.Between(0, 1) === 0 ? 'LEFT' : 'RIGHT';
}

function createLiftChoiceUi(scene) {
    const y = 520;
    const leftX = 320;
    const rightX = 480;

    const mkArrow = (x) => {
        const g = scene.add.graphics();
        g.setDepth(32);
        return { g, x };
    };

    const left = mkArrow(leftX);
    const right = mkArrow(rightX);

    const leftTxt = scene.add.text(leftX, y, '←', { fontFamily: 'Outfit', fontSize: '26px', color: '#ffffff' });
    leftTxt.setOrigin(0.5);
    leftTxt.setDepth(33);
    const rightTxt = scene.add.text(rightX, y, '→', { fontFamily: 'Outfit', fontSize: '26px', color: '#ffffff' });
    rightTxt.setOrigin(0.5);
    rightTxt.setDepth(33);

    const label = scene.add.text(COURT.centerX, y - 44, 'ESCOLHA O LADO DO LEVANTAMENTO', {
        fontFamily: 'Outfit',
        fontSize: '14px',
        fontStyle: 'italic',
        fontWeight: '700',
        color: '#ffffff',
        stroke: '#000000',
        strokeThickness: 4
    });
    label.setOrigin(0.5);
    label.setDepth(33);

    liftChoiceUi = { left, right, leftTxt, rightTxt, label, y };
    setLiftChoiceUiVisible(false);
}

function setLiftChoiceUiVisible(visible) {
    if (!liftChoiceUi) return;
    liftChoiceUi.left.g.setVisible(visible);
    liftChoiceUi.right.g.setVisible(visible);
    liftChoiceUi.leftTxt.setVisible(visible);
    liftChoiceUi.rightTxt.setVisible(visible);
    liftChoiceUi.label.setVisible(visible);
}

function updateLiftChoiceUi(_scene) {
    if (!liftChoiceUi) return;
    setLiftChoiceUiVisible(false);
}

function getGravityZForState() {
    if (state === 'TOSS' || state === 'HIT_WINDOW') return BALL.gravityZToss;
    if (state === 'OPP_SERVE_TOSS') return BALL.gravityZToss;

    if (state === 'FLYING' || state === 'BOUNCE_ROLL') {
        return getFlightGravityAtY(ballBody.y);
    }
    return BALL.gravityZ;
}

function getFlightGravityAtY(screenY) {
    // Smooth transition around the net to avoid a harsh curve kink.
    // w=0 -> player side, w=1 -> opponent side.
    const t = Phaser.Math.Clamp((NET.y - screenY) / 80, -1, 1);
    const w = 0.5 + 0.5 * t;
    return Phaser.Math.Linear(BALL.gravityZFlightPreNet, BALL.gravityZFlightPostNet, w);
}

function calculateLaunchVZForFlight(startZ, targetZ, startY, velocityY, flightTime) {
    const steps = 48;
    const stepTime = flightTime / steps;
    let gravityVelocity = 0;
    let gravityDisplacement = 0;

    for (let index = 0; index < steps; index += 1) {
        const middleTime = (index + 0.5) * stepTime;
        const gravity = getFlightGravityAtY(startY + velocityY * middleTime);
        gravityDisplacement += gravityVelocity * stepTime + 0.5 * gravity * stepTime * stepTime;
        gravityVelocity += gravity * stepTime;
    }

    return Phaser.Math.Clamp(
        (targetZ - startZ - gravityDisplacement) / flightTime,
        PHYS_CLAMP.minVZ,
        PHYS_CLAMP.maxVZ
    );
}

function updateLandingShadow(scene) {
    if (!landingShadow) return;

    if (state !== 'FLYING' && state !== 'BOUNCE_ROLL') {
        landingShadowState = 0;
        landingShadow.setVisible(false);
        return;
    }

    const landing = getPredictedLandingPoint();
    if (!landing) {
        landingShadow.setVisible(false);
        return;
    }

    const predictedY = landing.y;
    const predictedX = landing.x;

    const targetX = Phaser.Math.Clamp(predictedX, COURT.minX, COURT.maxX);
    const targetY = Phaser.Math.Clamp(predictedY, 0, COURT.height);

    // Smooth shadow movement to avoid sudden jumps
    const smooth = 1 - Math.pow(0.001, scene.game.loop.delta / 1000);
    smoothedLandingX += (targetX - smoothedLandingX) * smooth;
    smoothedLandingY += (targetY - smoothedLandingY) * smooth;

    landingShadow.x = smoothedLandingX;
    landingShadow.y = smoothedLandingY;

    // Tint to preview result: green inside opponent court, red when "out".
    const insideOpponentCourt = isLandingInsideOpponentCourt({ x: predictedX, y: predictedY });
    landingShadow.setTint(insideOpponentCourt ? 0x22ff88 : 0xff2b2b);

    const zRatio = Phaser.Math.Clamp(ballZ / BALL.tossTargetZ, 0, 1);
    landingShadow.setScale(0.8 + (1 - zRatio) * 0.55);

    // Hysteresis to prevent flicker near the threshold
    if (!landingCloseToGround && ballZ < 36) landingCloseToGround = true;
    if (landingCloseToGround && ballZ > 48) landingCloseToGround = false;

    if (landingCloseToGround && landingShadowState !== 1) {
        landingShadowState = 1;
        landingShadow.setTexture('shadow1');
    }
    landingShadow.setVisible(true);
}

function updateClosestNetCrossing(scene) {
    const depthAbs = Math.abs(ballBody.y - NET.y);
    if (!closestNetCrossing || depthAbs < closestNetCrossing.depthAbs) {
        closestNetCrossing = {
            depthAbs,
            x: ballBody.x,
            z: ballZ,
            time: scene.time.now
        };
    }

    // Fallback: if exact crossing was not detected, record when the ball is close enough to Y0.
    if (!hasRecordedNetCrossing && depthAbs <= 12) {
        addNetHeightMarker(scene, ballBody.x, ballZ, ballZ >= NET.heightZ);
        hasRecordedNetCrossing = true;
    }
}

function isBallMovingBetweenSides() {
    if (!ballBody?.body) return false;
    const vy = ballBody.body.velocity.y;
    if (Math.abs(vy) < 5) return false;
    return true;
}

function didOpponentTouchLast() {
    return lastTouch === 'OPP_HIT' || lastTouch === 'OPP_PASS' || lastTouch === 'OPP_SET';
}

function getPointWinnerAfterLastTouchFault() {
    return didOpponentTouchLast();
}

function handleNetFault(scene) {
    state = 'NET_HIT';
    trail.stop();
    ballBody.setVelocity(0, didOpponentTouchLast() ? -120 : 120);
    ballVZ = -108;
    ball.setTint(0xff2626);
    landingShadowState = 0;
    landingShadow.setVisible(false);
    scene.cameras.main.shake(130, 0.012);
    finishShotLog('bateu na rede');
    scene.time.delayedCall(450, () => awardPoint(scene, getPointWinnerAfterLastTouchFault(), 'net'));
}

function isNetFaultByClosestCrossing() {
    if (!closestNetCrossing) return false;
    if (closestNetCrossing.depthAbs > 36) return false;
    return closestNetCrossing.z < NET.heightZ;
}

function formatCourtDebugTable() {
    const difficulty = getDifficultyProfile();
    const quadrants = [
        ['ADVERSARIO_ESQUERDA', COURT.minX, COURT.centerX, OPPONENT_COURT.minY, OPPONENT_AI.zoneSplitY],
        ['ADVERSARIO_DIREITA', COURT.centerX, COURT.maxX, OPPONENT_COURT.minY, OPPONENT_AI.zoneSplitY],
        ['JOGADOR_ESQUERDA', COURT.minX, COURT.centerX, NET.y, COURT.maxY],
        ['JOGADOR_DIREITA', COURT.centerX, COURT.maxX, NET.y, COURT.maxY]
    ];
    return [
        '| item | valor |',
        '| --- | --- |',
        `| adversario | ${getCurrentOpponent().name.toUpperCase()} |`,
        `| dificuldade | ${difficulty.label.toUpperCase()} |`,
        `| quadra oficial | ${VOLLEYBALL_DIMENSIONS.courtLengthMeters} x ${VOLLEYBALL_DIMENSIONS.courtWidthMeters} m |`,
        `| meia quadra oficial | ${VOLLEYBALL_DIMENSIONS.halfCourtMeters} m |`,
        `| altura oficial da rede | ${VOLLEYBALL_DIMENSIONS.netHeightMeters.toFixed(2)} m |`,
        `| canvas | ${COURT.width} x ${COURT.height} px |`,
        `| largura jogavel X | ${COURT.minX} ate ${COURT.maxX} (${COURT.maxX - COURT.minX}px) |`,
        `| distancia total Y | ${OPPONENT_COURT.minY} ate ${COURT.maxY} (${COURT.maxY - OPPONENT_COURT.minY}px) |`,
        `| centro X | ${COURT.centerX} |`,
        `| rede Y | ${NET.y} |`,
        `| altura da rede Z | ${NET.heightZ} |`,
        `| escala Z | ${BALL.zToPixels} px por Z |`,
        `| gravidade saque jogador | ${BALL.gravityZFlightPreNet} Z/s2 |`,
        `| gravidade lado adversario | ${BALL.gravityZFlightPostNet} Z/s2 |`,
        `| raio recepcao IA | ${difficulty.receiveRadius} |`,
        `| atraso reacao IA | ${difficulty.reactionDelayMs} ms |`,
        `| tempo saque IA | ${difficulty.serve.flightTime.toFixed(2)} s |`,
        `| tempo ataque IA | ${difficulty.attack.flightTime.toFixed(2)} s |`,
        `| lado adversario | Y ${OPPONENT_COURT.minY} ate ${OPPONENT_COURT.maxY} |`,
        `| lado jogador | Y ${NET.y} ate ${COURT.maxY} |`,
        '',
        '| quadrante | X tela | Y tela | X quadra | Y profundidade |',
        '| --- | --- | --- | --- | --- |',
        ...quadrants.map(([name, minX, maxX, minY, maxY]) => `| ${name} | ${minX} ate ${maxX} | ${minY} ate ${maxY} | ${screenXToWidth(minX)} ate ${screenXToWidth(maxX)} | ${screenYToDepth(minY)} ate ${screenYToDepth(maxY)} |`),
        ''
    ];
}

function startRallyLog(server) {
    if (!isAdminDebugEnabled()) {
        rallyLog = null;
        completedShotLogs = [];
        activeShotLog = null;
        return;
    }

    rallyLogIndex += 1;
    completedShotLogs = [];
    activeShotLog = null;
    rallyLog = {
        id: rallyLogIndex,
        server,
        startedAt: new Date().toISOString(),
        lines: [
            `LOG DEBUG VOLEI #${rallyLogIndex}`,
            `inicio do jogo: ${new Date().toLocaleString('pt-BR')}`,
            `status: SAQUE`,
            `sacador: ${server}`,
            `adversario: ${getCurrentOpponent().name.toUpperCase()}`,
            `dificuldade: ${getDifficultyProfile().label.toUpperCase()}`,
            '',
            'TABELA DA QUADRA',
            ...formatCourtDebugTable()
        ]
    };
}
function beginShotLog(type, actor, details = {}) {
    if (!isAdminDebugEnabled()) return;
    if (activeShotLog) finishShotLog(`encerrado ao iniciar ${type}`);
    activeShotLog = {
        type,
        actor,
        details,
        touch: {
            screenX: Math.round(ballBody.x),
            screenY: Math.round(ballBody.y),
            widthX: screenXToWidth(ballBody.x),
            depthY: screenYToDepth(ballBody.y),
            z: Math.round(ballZ),
            playerZ: Math.round(playerZ || 0)
        },
        velocity: null,
        calculations: null,
        net: null,
        landing: null,
        receive: null,
        result: null
    };
}

function setShotCalculations(calculations) {
    if (!activeShotLog) return;
    activeShotLog.calculations = calculations;
}

function setShotVelocity(vx, vy, vz = ballVZ) {
    if (!activeShotLog) return;
    activeShotLog.velocity = {
        vx: Math.round(vx),
        vy: Math.round(vy),
        vz: Math.round(vz),
        speed: Math.round(Math.hypot(vx, vy, vz))
    };
}

function recordShotNet(zAtNet, cleared) {
    if (!activeShotLog) return;
    activeShotLog.net = {
        z: Math.round(zAtNet),
        netHeightZ: NET.heightZ,
        aboveNetZ: Math.round(zAtNet - NET.heightZ),
        cleared
    };
}

function ensureShotNetFromCurrentTrajectory(source = 'calculo') {
    if (!activeShotLog || !ballBody?.body) return;
    if (activeShotLog.net && activeShotLog.net.z !== undefined) return;

    const vy = ballBody.body.velocity.y;
    if (Math.abs(vy) < 1) return;

    const tToNet = (NET.y - ballBody.y) / vy;
    if (!Number.isFinite(tToNet) || tToNet < 0) return;

    const gravity = getGravityZForState();
    const zAtNet = ballZ + ballVZ * tToNet + 0.5 * gravity * tToNet * tToNet;
    const xAtNet = ballBody.x + ballBody.body.velocity.x * tToNet;
    activeShotLog.net = {
        z: Math.round(zAtNet),
        netHeightZ: NET.heightZ,
        aboveNetZ: Math.round(zAtNet - NET.heightZ),
        cleared: zAtNet >= NET.heightZ,
        x: Math.round(xAtNet),
        widthX: screenXToWidth(xAtNet),
        time: `${tToNet.toFixed(3)}s`,
        source
    };
}

function recordShotLanding() {
    if (!activeShotLog) return;
    activeShotLog.landing = {
        screenX: Math.round(ballBody.x),
        screenY: Math.round(ballBody.y),
        widthX: screenXToWidth(ballBody.x),
        depthY: screenYToDepth(ballBody.y),
        side: ballBody.y < NET.y ? 'ADVERSARIO' : 'JOGADOR'
    };
}

function recordOpponentReceiveDebug(receiver, action, details = {}) {
    if (!activeShotLog || !receiver || !ballBody) return;
    const dist = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, receiver.x, receiver.y);
    activeShotLog.receive = {
        action,
        receiverX: Math.round(receiver.x),
        receiverY: Math.round(receiver.y),
        receiverWidthX: screenXToWidth(receiver.x),
        receiverDepthY: screenYToDepth(receiver.y),
        ballX: Math.round(ballBody.x),
        ballY: Math.round(ballBody.y),
        ballWidthX: screenXToWidth(ballBody.x),
        ballDepthY: screenYToDepth(ballBody.y),
        ballZ: Math.round(ballZ),
        distance: Math.round(dist),
        ...details
    };
}

function finishShotLog(result = '') {
    if (!activeShotLog) return;
    ensureShotNetFromCurrentTrajectory('fim da jogada');
    activeShotLog.result = result;
    completedShotLogs.push(activeShotLog);
    activeShotLog = null;
}

function formatShotLog(shot, index) {
    const d = shot.details || {};
    const v = shot.velocity || {};
    const n = shot.net || {};
    const l = shot.landing || {};
    const r = shot.receive || {};
    const c = shot.calculations || {};
    const calculationLines = Object.keys(c).length
        ? [
            'CALCULOS',
            ...Object.entries(c).map(([key, value]) => `${key}: ${value}`),
            ''
        ]
        : [];
    return [
        `JOGADA ${index}: ${shot.type}`,
        ...calculationLines,
        `ator: ${shot.actor}`,
        `forca: ${d.force ?? '---'}`,
        `qualidade: ${d.quality ?? '---'}`,
        `altura toque bola Z: ${shot.touch.z}`,
        `altura player ao bater Z: ${shot.touch.playerZ ?? '---'}`,
        `ponto toque: X ${shot.touch.widthX} | Y ${shot.touch.depthY} (screen ${shot.touch.screenX}, ${shot.touch.screenY})`,
        `velocidade: vx ${v.vx ?? '---'} | vy ${v.vy ?? '---'} | vz ${v.vz ?? '---'} | total ${v.speed ?? '---'}`,
        `cruzamento rede: X ${n.widthX ?? '---'} | Y 0 | Z ${n.z ?? '---'} (screen ${n.x ?? '---'}, ${NET.y})`,
        `tempo ate rede real: ${n.time ?? '---'} | origem rede: ${n.source ?? '---'}`,
        `altura da rede Z: ${n.netHeightZ ?? NET.heightZ}`,
        `folga acima da rede Z: ${n.aboveNetZ ?? '---'}`,
        `passou da rede: ${n.cleared === undefined ? '---' : n.cleared ? 'SIM' : 'NAO'}`,
        `toque no chao: X ${l.widthX ?? '---'} | Y ${l.depthY ?? '---'} | lado ${l.side ?? '---'} (screen ${l.screenX ?? '---'}, ${l.screenY ?? '---'})`,
        `manchete adversaria: ${r.action ?? '---'}`,
        `ponto manchete: X ${r.ballWidthX ?? '---'} | Y ${r.ballDepthY ?? '---'} | Z ${r.ballZ ?? '---'} (screen ${r.ballX ?? '---'}, ${r.ballY ?? '---'})`,
        `distancia bola-adversario na manchete: ${r.distance ?? '---'}`,
        `receptor adversario: X ${r.receiverWidthX ?? '---'} | Y ${r.receiverDepthY ?? '---'} (screen ${r.receiverX ?? '---'}, ${r.receiverY ?? '---'})`,
        `raio recepcao: ${r.receiveRadius ?? '---'} | chance: ${r.successChance ?? '---'} | resultado recepcao: ${r.receiveResult ?? '---'}`,
        `resultado: ${shot.result || '---'}`,
        ''
    ].join('\n');
}

async function finalizeAndDownloadRallyLog(pointWinner) {
    if (!rallyLog) return;
    if (activeShotLog) finishShotLog('finalizado junto com o ponto');

    const lines = [...rallyLog.lines];
    completedShotLogs.forEach((shot, index) => lines.push(formatShotLog(shot, index + 1)));
    lines.push(`vencedor do ponto: ${pointWinner}`);
    lines.push(`placar: jogador ${score.player} x ${score.opponent} adversario`);
    lines.push(`sets: jogador ${score.playerSets} x ${score.opponentSets} adversario`);
    lines.push(`fim do jogo: ${new Date().toLocaleString('pt-BR')}`);

    const text = lines.join('\n');
    lastCompletedLogText = text;
    adminDebugLogHistory.push(text);
    if (adminDebugLogHistory.length > 30) adminDebugLogHistory.shift();
    updateAdminDebugLogModal();
    lastCompletedLogName = `volei-log-${String(rallyLog.id).padStart(3, '0')}.txt`;
    try {
        localStorage.setItem('ultimoLogVolei', text);
    } catch (_err) {
        // LocalStorage can be disabled for file URLs in some browsers.
    }

    await saveDebugLogToServer(lastCompletedLogName, text);
}

async function saveDebugLogToServer(filename, text) {
    lastLogSaveError = '';
    if (!isAdminDebugEnabled()) return false;
    if (window.location.protocol === 'file:') {
        lastLogSaveError = 'abra pelo Browser em http://127.0.0.1:8080/';
        return false;
    }

    try {
        const response = await fetch('save_debug_log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ filename, text })
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = await response.json();
        if (!result.ok) throw new Error(result.error || 'falha ao salvar');
        lastDebugSavePath = result.file || '';
        return true;
    } catch (err) {
        lastLogSaveError = err && err.message ? err.message : String(err);
        console.warn('Nao foi possivel salvar log.txt automaticamente:', lastLogSaveError);
        return false;
    }
}

async function saveMatchResultToServer(matchResult) {
    if (!matchResult?.winner || matchResultSubmitting) return false;
    matchResultSubmitting = true;

    try {
        if (campaignState.loggedIn) {
            if (!activeMatchToken) throw new Error('Partida sem identificação. Recarregue a página.');
            const result = await postCampaign('result', {
                winner: matchResult.winner,
                playerSets: matchResult.playerSets,
                opponentSets: matchResult.opponentSets,
                matchToken: activeMatchToken
            });
            activeMatchToken = null;
            applyCampaignState(result.state);
        } else {
            const playerWon = matchResult.winner === 'PLAYER';
            const currentOpponent = campaignState.opponentIndex;
            applyCampaignState({
                ...campaignState,
                wins: campaignState.wins + (playerWon ? 1 : 0),
                losses: campaignState.losses + (playerWon ? 0 : 1),
                opponentIndex: playerWon ? Math.min(OPPONENT_TEAMS.length - 1, currentOpponent + 1) : currentOpponent,
                campaignCompleted: campaignState.campaignCompleted || (playerWon && currentOpponent === OPPONENT_TEAMS.length - 1)
            });
            activeMatchToken = null;
            saveGuestCampaign();
        }

        unsavedMatchResult = null;
        return true;
    } catch (error) {
        unsavedMatchResult = matchResult;
        console.warn('Nao foi possivel salvar o resultado da partida:', error?.message || String(error));
        throw error;
    } finally {
        matchResultSubmitting = false;
    }
}

function estimateTimeToGround(z, vz, az) {
    // Solve: z + vz*t + 0.5*az*t^2 = 0
    const a = 0.5 * az;
    const b = vz;
    const c = z;

    if (Math.abs(a) < 1e-6) {
        if (Math.abs(b) < 1e-6) return Infinity;
        const t = -c / b;
        return t > 0 ? t : Infinity;
    }

    const disc = b * b - 4 * a * c;
    if (disc < 0) return Infinity;
    const s = Math.sqrt(disc);
    const t1 = (-b - s) / (2 * a);
    const t2 = (-b + s) / (2 * a);
    const t = Math.min(t1, t2) > 0 ? Math.min(t1, t2) : Math.max(t1, t2);
    return t > 0 ? t : Infinity;
}

function getFrameIndependentLerp(ratePerSecond, deltaSeconds) {
    return 1 - Math.exp(-ratePerSecond * deltaSeconds);
}

function moveOpponentReceiver(sprite, home, ratePerSecond, deltaSeconds) {
    if (!sprite || !ballBody) return;
    const minX = Math.max(home.x - home.rangeX, home.zone?.minX ?? -Infinity);
    const maxX = Math.min(home.x + home.rangeX, home.zone?.maxX ?? Infinity);
    const minY = Math.max(home.y - home.rangeY, home.zone?.minY ?? -Infinity);
    const maxY = Math.min(home.y + home.rangeY, home.zone?.maxY ?? Infinity);
    const targetX = Phaser.Math.Clamp(ballBody.x, minX, maxX);
    const targetY = Phaser.Math.Clamp(ballBody.y, minY, maxY);
    const amount = getFrameIndependentLerp(ratePerSecond, deltaSeconds);
    sprite.x += (targetX - sprite.x) * amount;
    sprite.y += (targetY - sprite.y) * amount;
}

function moveOrReturnOpponentReceiver(sprite, home, active, ratePerSecond, deltaSeconds) {
    if (active) moveOpponentReceiver(sprite, home, ratePerSecond, deltaSeconds);
    else returnOpponentToHome(sprite, home, 4, deltaSeconds);
}

function returnOpponentToHome(sprite, home, ratePerSecond, deltaSeconds) {
    if (!sprite) return;
    const amount = getFrameIndependentLerp(ratePerSecond, deltaSeconds);
    sprite.x += (home.x - sprite.x) * amount;
    sprite.y += (home.y - sprite.y) * amount;
}

function canOpponentSetterTakeLowServe() {
    if (lastPlayerShotKind !== 'SERVE') return false;
    if (!ballBody?.body) return false;
    if (ballBody.body.velocity.y >= 0) return false;

    const lowServeMinZ = NET.heightZ + 1;
    const lowServeMaxZ = NET.heightZ + 18;
    const nearNetMinY = NET.y - 44;
    const nearNetMaxY = NET.y + 10;

    const inReach = (
        ballZ >= lowServeMinZ &&
        ballZ <= lowServeMaxZ &&
        ballBody.y >= nearNetMinY &&
        ballBody.y <= nearNetMaxY &&
        Math.abs(ballBody.x - opponentFront.x) <= OPPONENT_AI.setRadius + 34
    );
    if (!inReach) return false;

    if (opponentLowServeDecision === null) {
        opponentLowServeDecision = Math.random() <= getDifficultyProfile().lowServeSetterChance;
    }
    return opponentLowServeDecision;
}

function opponentSetterTakeLowServe(scene) {
    opponentReceiveAttemptedForPlayerHit = true;
    lastTouch = 'OPP_PASS';
    ballBody.x = opponentFront.x;
    ballBody.y = opponentFront.y;
    ballZ = Math.max(ballZ, NET.heightZ + 8);
    ballBody.setVelocity(0);

    setShotCalculations({
        ...(activeShotLog?.calculations || {}),
        'decisao defesa IA': 'levantador pegou saque baixo',
        'faixa saque baixo': `${NET.heightZ + 1} ate ${NET.heightZ + 18}`,
        'altura contato levantador': Math.round(ballZ)
    });

    opponentFront.setTexture('oppLevantador');
    opponentPassToSetterEvent = scene.time.delayedCall(180, () => {
        opponentPassToSetterEvent = null;
        if (state === 'FLYING' && lastTouch === 'OPP_PASS') opponentSetToAttacker(scene);
    });
    scene.cameras.main.shake(70, 0.003);
    addHitEffect(scene, { shake: 0.002 });
}

function getOpponentReceiverForBall() {
    if (!ballBody) return null;
    if (isBallInOpponentZone(OPPONENT_AI.backHome.zone)) return opponentBack;
    if (isBallInOpponentZone(OPPONENT_AI.leftHome.zone)) return oppLeft;
    if (isBallInOpponentZone(OPPONENT_AI.rightHome.zone)) return oppRight;
    return null;
}

function isBallInOpponentZone(zone) {
    if (!zone || !ballBody) return false;
    return (
        ballBody.x >= zone.minX &&
        ballBody.x <= zone.maxX &&
        ballBody.y >= zone.minY &&
        ballBody.y <= zone.maxY
    );
}

function getPredictedLandingPoint() {
    if (!ballBody?.body) return null;

    const stepTime = 1 / 120;
    const maxFlightTime = 4;
    let elapsed = 0;
    let predictedX = ballBody.x;
    let predictedY = ballBody.y;
    let predictedZ = ballZ;
    let predictedVZ = ballVZ;
    const velocityX = ballBody.body.velocity.x;
    const velocityY = ballBody.body.velocity.y;

    while (elapsed < maxFlightTime) {
        predictedX += velocityX * stepTime;
        predictedY += velocityY * stepTime;
        predictedZ += predictedVZ * stepTime;
        predictedVZ += getFlightGravityAtY(predictedY) * stepTime;
        elapsed += stepTime;

        if (predictedZ <= 0) {
            return { x: predictedX, y: predictedY, flightTime: elapsed };
        }
    }

    return null;
}

function getOpponentCourtXBounds(screenY, margin = 0) {
    const perspective = Phaser.Math.Clamp(
        (screenY - OPPONENT_COURT.backLineY) /
            (OPPONENT_COURT.netLineY - OPPONENT_COURT.backLineY),
        0,
        1
    );
    return {
        minX: Phaser.Math.Linear(OPPONENT_COURT.backMinX, OPPONENT_COURT.netMinX, perspective) - margin,
        maxX: Phaser.Math.Linear(OPPONENT_COURT.backMaxX, OPPONENT_COURT.netMaxX, perspective) + margin
    };
}

function isLandingInsideOpponentCourt(point, margin = 0) {
    if (!point) return true;
    const bounds = getOpponentCourtXBounds(point.y, margin);

    return (
        point.x >= bounds.minX &&
        point.x <= bounds.maxX &&
        point.y >= OPPONENT_COURT.minY - margin &&
        point.y <= OPPONENT_COURT.maxY + margin
    );
}

function shouldOpponentLetPlayerShotOut() {
    if (lastTouch !== 'PLAYER_HIT') return false;
    if (opponentLetOutDecision !== null) return opponentLetOutDecision;

    const landing = getPredictedLandingPoint();
    if (!landing) return false;

    opponentLetOutPrediction = landing;
    opponentLetOutDecision = !isLandingInsideOpponentCourt(landing, 8);
    return opponentLetOutDecision;
}

function markOpponentLetOutPrediction() {
    const landing = opponentLetOutPrediction || getPredictedLandingPoint();
    if (!landing || opponentLetOutLogged) return;
    const bounds = getOpponentCourtXBounds(landing.y, 8);
    opponentLetOutLogged = true;

    setShotCalculations({
        ...(activeShotLog?.calculations || {}),
        'decisao defesa IA': 'deixou sair',
        'previsao queda IA': `x ${Math.round(landing.x)} y ${Math.round(landing.y)}`,
        'limites laterais previstos': `x ${Math.round(bounds.minX)} ate ${Math.round(bounds.maxX)}`,
        'limites profundidade': `y ${OPPONENT_COURT.minY} ate ${OPPONENT_COURT.maxY}`
    });
}

function resetOpponentLetOutDecision() {
    opponentLetOutDecision = null;
    opponentLetOutPrediction = null;
    opponentLetOutLogged = false;
}

function canOpponentReceive(sprite, home) {
    if (!sprite || !ballBody) return false;
    const difficulty = getDifficultyProfile();
    const insideRange =
        ballBody.x >= home.x - home.rangeX &&
        ballBody.x <= home.x + home.rangeX &&
        ballBody.y >= home.y - home.rangeY &&
        ballBody.y <= home.y + home.rangeY;
    const insideZone = isBallInOpponentZone(home.zone);
    const receiveRadius = lastPlayerShotKind === 'SERVE'
        ? difficulty.receiveRadius + difficulty.serveReceiveBonus
        : difficulty.receiveRadius;
    const maxReceiveZ = lastPlayerShotKind === 'SERVE' ? 145 : 130;
    const closeEnough = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, sprite.x, sprite.y) < receiveRadius;
    return insideZone && insideRange && closeEnough && ballZ < maxReceiveZ && ballZ > 8;
}

function tryOpponentReceivePlayerAttack(scene, receiver) {
    if (lastTouch !== 'PLAYER_HIT') return true;
    if (opponentReceiveAttemptedForPlayerHit) return false;

    opponentReceiveAttemptedForPlayerHit = true;
    const isServeReceive = lastPlayerShotKind === 'SERVE';
    const difficulty = getDifficultyProfile();
    if (isServeReceive) {
        const correction = currentDifficultyKey === 'easy' ? 0.12 : currentDifficultyKey === 'advanced' ? 0.58 : 0.45;
        receiver.x += (ballBody.x - receiver.x) * correction;
        receiver.y += (ballBody.y - receiver.y) * correction;
    }

    const dist = Phaser.Math.Distance.Between(ballBody.x, ballBody.y, receiver.x, receiver.y);
    const receiveRadius = isServeReceive
        ? difficulty.receiveRadius + difficulty.serveReceiveBonus
        : difficulty.receiveRadius;
    const distanceRatio = Phaser.Math.Clamp(dist / receiveRadius, 0, 1);
    const receiveTuning = isServeReceive ? difficulty.receive.serve : difficulty.receive.attack;
    let successChance = Phaser.Math.Clamp(
        receiveTuning.base -
        (lastPlayerAttackPower * receiveTuning.powerPenalty) -
        (distanceRatio * receiveTuning.distancePenalty),
        receiveTuning.min,
        receiveTuning.max
    );
    if (isServeReceive && dist <= 28 && ballZ <= 145) {
        successChance = Math.max(successChance, receiveTuning.closeMin);
    }

    setShotCalculations({
        ...(activeShotLog?.calculations || {}),
        'chance defesa IA': `${Math.round(successChance * 100)}%`,
        'dificuldade IA': difficulty.label,
        'tipo ataque jogador': lastPlayerShotKind,
        'forca ataque jogador': lastPlayerAttackPower.toFixed(2),
        'distancia defesa IA': Math.round(dist)
    });

    if (Math.random() <= successChance) {
        recordOpponentReceiveDebug(receiver, 'MACHETE', {
            receiveRadius: Math.round(receiveRadius),
            successChance: `${Math.round(successChance * 100)}%`,
            receiveResult: 'recebida e levantada'
        });
        finishShotLog(isServeReceive ? 'saque recebido pela IA' : 'ataque recebido pela IA');
        return true;
    }

    const missedOutChance = isServeReceive
        ? difficulty.receiveMissOutChance.serve
        : difficulty.receiveMissOutChance.attack;
    const missedOut = Math.random() < missedOutChance;
    receiver.setTexture('oppMachete');
    scene.time.delayedCall(500, () => {
        if (receiver.texture && receiver.texture.key === 'oppMachete') receiver.setTexture('oppParado');
    });
    scene.cameras.main.shake(150, 0.01);
    addHitEffect(scene, { shake: 0.009 });

    lastTouch = 'OPP_PASS';
    ballBody.x = receiver.x;
    ballBody.y = receiver.y;

    if (missedOut) {
        recordOpponentReceiveDebug(receiver, 'MACHETE', {
            receiveRadius: Math.round(receiveRadius),
            successChance: `${Math.round(successChance * 100)}%`,
            receiveResult: 'bateu nos bracos e saiu'
        });
        ballVZ = isServeReceive ? 105 : 120;
        const side = receiver.x < COURT.centerX ? -1 : 1;
        ballBody.setVelocity(side * 300, isServeReceive ? -180 : -260);
        finishShotLog(isServeReceive ? 'recepcao de saque para fora' : 'defesa adversaria para fora');
        return false;
    }

    if (isServeReceive) {
        recordOpponentReceiveDebug(receiver, 'MACHETE', {
            receiveRadius: Math.round(receiveRadius),
            successChance: `${Math.round(successChance * 100)}%`,
            receiveResult: 'recepcao ruim para o levantador'
        });
        ballZ = Math.max(ballZ, 28);
        ballVZ = 220;
        const flightTime = 0.95;
        const vx = (opponentFront.x - ballBody.x) / flightTime;
        const vy = (opponentFront.y - ballBody.y) / flightTime;
        ballBody.setVelocity(vx, vy);
        finishShotLog('recepcao de saque ruim');
        return false;
    }

    recordOpponentReceiveDebug(receiver, 'MACHETE', {
        receiveRadius: Math.round(receiveRadius),
        successChance: `${Math.round(successChance * 100)}%`,
        receiveResult: 'falhou na defesa do ataque'
    });
    finishShotLog('defesa adversaria falhou');
    return false;
}

function opponentPassToSetter(scene, receiver) {
    const difficulty = getDifficultyProfile();
    recordOpponentReceiveDebug(receiver, 'MACHETE', {
        receiveRadius: Math.round(difficulty.receiveRadius),
        successChance: '100%',
        receiveResult: 'passe para o levantador'
    });
    lastTouch = 'OPP_PASS';
    const targetX = opponentFront.x;
    const targetY = opponentFront.y;
    const flightTime = difficulty.passFlightTime;
    const targetContactZ = 58;
    ballBody.x = receiver.x;
    ballBody.y = receiver.y;
    ballZ = Math.max(ballZ, 34);
    const vx = (targetX - ballBody.x) / flightTime;
    const vy = (targetY - ballBody.y) / flightTime;
    ballVZ = calculateLaunchVZForFlight(ballZ, targetContactZ, ballBody.y, vy, flightTime);
    ballBody.setVelocity(vx, vy);

    if (opponentPassToSetterEvent) {
        opponentPassToSetterEvent.remove(false);
        opponentPassToSetterEvent = null;
    }
    opponentPassToSetterEvent = scene.time.delayedCall(Math.round(flightTime * 1000), () => {
        opponentPassToSetterEvent = null;
        if (state !== 'FLYING' || lastTouch !== 'OPP_PASS') return;
        ballBody.x = opponentFront.x;
        ballBody.y = opponentFront.y;
        ballZ = targetContactZ;
        ballBody.setVelocity(0);
        opponentSetToAttacker(scene);
    });

    receiver.setTexture('oppMachete');
    scene.time.delayedCall(650, () => {
        if (receiver.texture && receiver.texture.key === 'oppMachete') receiver.setTexture('oppParado');
    });
    scene.cameras.main.shake(90, 0.004);
    addHitEffect(scene, { shake: 0.003 });
}

function opponentSetToAttacker(scene) {
    const difficulty = getDifficultyProfile();
    if (opponentPassToSetterEvent) {
        opponentPassToSetterEvent.remove(false);
        opponentPassToSetterEvent = null;
    }
    if (opponentSetAttackEvent) {
        opponentSetAttackEvent.remove(false);
        opponentSetAttackEvent = null;
    }
    lastTouch = 'OPP_SET';
    opponentAttacker = chooseRandomOpponentAttacker();
    const flightTime = difficulty.setFlightTime;
    const targetContactZ = difficulty.attack.contactZ;
    const targetX = opponentAttacker.x;
    const targetY = opponentAttacker.y;
    const vx = (targetX - ballBody.x) / flightTime;
    const vy = (targetY - ballBody.y) / flightTime;
    ballVZ = calculateLaunchVZForFlight(ballZ, targetContactZ, ballBody.y, vy, flightTime);
    ballBody.setVelocity(vx, vy);

    opponentFront.setTexture('oppLevantador');
    scene.time.delayedCall(650, () => opponentFront.setTexture('oppParado'));
    opponentSetAttackEvent = scene.time.delayedCall(Math.round(flightTime * 1000), () => {
        opponentSetAttackEvent = null;
        if (state !== 'FLYING' || lastTouch !== 'OPP_SET') return;
        ballBody.x = opponentAttacker.x;
        ballBody.y = opponentAttacker.y;
        ballZ = targetContactZ;
        performOpponentAttack(scene, opponentAttacker);
    });
    scene.cameras.main.shake(90, 0.004);
    addHitEffect(scene, { shake: 0.003 });
}

function chooseRandomOpponentAttacker() {
    return Phaser.Math.Between(0, 1) === 0 ? oppLeft : oppRight;
}

function performOpponentAttack(scene, attacker) {
    if (opponentSetAttackEvent) {
        opponentSetAttackEvent.remove(false);
        opponentSetAttackEvent = null;
    }
    const startY = attacker.y;
    attacker.y -= 45;

    const difficulty = getDifficultyProfile();
    const attackTuning = difficulty.attack;
    ballZ = attackTuning.contactZ;
    lastTouch = 'OPP_HIT';
    const attackError = Math.random() < attackTuning.errorChance;
    beginShotLog('ATAQUE', 'ADVERSARIO', {
        force: difficulty.label,
        quality: attackError ? 'erro de ataque' : 'terceiro toque'
    });
    const flightTime = attackTuning.flightTime;
    const targetCenterX = currentDifficultyKey === 'easy'
        ? playerBacker.x
        : currentDifficultyKey === 'advanced'
            ? (playerBacker.x < COURT.centerX ? COURT.maxX - 55 : COURT.minX + 55)
            : attacker.x;
    const targetX = attackError
        ? (Phaser.Math.Between(0, 1) === 0 ? COURT.minX - 72 : COURT.maxX + 72)
        : Phaser.Math.Clamp(
            targetCenterX + Phaser.Math.Between(-attackTuning.spreadX, attackTuning.spreadX),
            COURT.minX + 30,
            COURT.maxX - 30
        );
    const targetY = Phaser.Math.Clamp(
        NET.y + Phaser.Math.Between(attackTuning.minDepth, attackTuning.maxDepth),
        NET.y + attackTuning.minDepth,
        NET.y + attackTuning.maxDepth
    );
    const vx = (targetX - ballBody.x) / flightTime;
    const vy = (targetY - ballBody.y) / flightTime;
    const attackGravityZ = getFlightGravityAtY(ballBody.y);
    ballVZ = calculateLaunchVZForFlight(ballZ, 0, ballBody.y, vy, flightTime);
    ballBody.setVelocity(
        Phaser.Math.Clamp(vx, -520, 520),
        Phaser.Math.Clamp(vy, 80, 720)
    );
    setShotCalculations({
        'modelo': 'ataque IA',
        'dificuldade IA': difficulty.label,
        'erro sorteado': attackError ? 'SIM' : 'NAO',
        'origem': `x ${Math.round(ballBody.x)} y ${Math.round(ballBody.y)} z ${Math.round(ballZ)}`,
        'alvo': `x ${Math.round(targetX)} y ${Math.round(targetY)}`,
        'tempo voo': `${flightTime.toFixed(3)}s`,
        'formula vx': `(${Math.round(targetX)} - ${Math.round(ballBody.x)}) / ${flightTime.toFixed(3)} = ${Math.round(vx)}`,
        'formula vy': `(${Math.round(targetY)} - ${Math.round(ballBody.y)}) / ${flightTime.toFixed(3)} = ${Math.round(vy)}`,
        'velocidade aplicada': `vx ${Math.round(ballBody.body.velocity.x)} vy ${Math.round(ballBody.body.velocity.y)} vz ${Math.round(ballVZ)}`,
        'velocidade total aplicada': Math.round(Math.hypot(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ)),
        'gravidade voo': attackGravityZ,
        'formula vz': `integracao da gravidade variavel por ${flightTime.toFixed(3)}s`
    });
    setShotVelocity(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ);
    lastOppAttackPlan = {
        fromX: ballBody.x,
        fromY: ballBody.y,
        toX: targetX,
        toY: targetY,
        vx,
        vy,
        flightTime,
        gravityZ: attackGravityZ,
        ballZ,
        ballVZ,
        t: scene.time.now
    };

    attacker.setTexture('oppAtaque');
    scene.time.delayedCall(600, () => {
        if (attacker.texture && attacker.texture.key === 'oppAtaque') attacker.setTexture('oppParado');
        attacker.y = startY;
    });

    scene.cameras.main.shake(200, 0.015);
    addHitEffect(scene, { shake: 0.01 });
}

function isPlayerAttackOutOnOpponentCourt() {
    if (lastTouch !== 'PLAYER_HIT') return false;
    if (!ballBody) return false;

    return !isLandingInsideOpponentCourt({ x: ballBody.x, y: ballBody.y }, 8);
}

function isOpponentAttackOutOnPlayerCourt() {
    if (lastTouch !== 'OPP_HIT') return false;
    if (!ballBody) return false;

    const marginX = 10;
    return (
        ballBody.y < NET.y ||
        ballBody.y > COURT.maxY ||
        ballBody.x < COURT.minX - marginX ||
        ballBody.x > COURT.maxX + marginX
    );
}

function finishRally(scene) {
    awardPoint(scene, ballBody.y < NET.y);
}

function awardPoint(scene, playerScored, reason = 'point') {
    if (pointLocked) return;
    pointLocked = true;
    cancelOpponentServeEvents();
    nextServer = playerScored ? 'PLAYER' : 'OPPONENT';
    state = 'POINT';
    const pointResult = addPoint(playerScored);
    finalizeAndDownloadRallyLog(playerScored ? 'PLAYER' : 'OPPONENT');
    const completedMatch = pointResult.matchWinner ? {
        winner: pointResult.matchWinner,
        playerSets: score.playerSets,
        opponentSets: score.opponentSets
    } : null;
    const saveResultPromise = completedMatch
        ? saveMatchResultToServer(completedMatch).catch((error) => ({ error }))
        : null;
    if (pointResult.matchWinner) {
        showStatusMessage(scene, pointResult.matchWinner === 'PLAYER' ? 'VITORIA' : 'DERROTA', 1800, pointResult.matchWinner === 'PLAYER' ? '#22ff88' : '#ff2626');
    } else if (pointResult.setWinner) {
        showStatusMessage(scene, pointResult.setWinner === 'PLAYER' ? 'SET' : 'SET RIVAL', 1600, pointResult.setWinner === 'PLAYER' ? '#22ff88' : '#ff4d4d');
    } else if (reason === 'net' && !playerScored) {
        showStatusMessage(scene, 'REDE', 1400, '#ff2626');
    } else if (reason === 'out') {
        showStatusMessage(scene, 'FORA', 1400, '#ff2626');
    } else {
        showStatusMessage(scene, 'PONTO', 1400);
    }

    if (ballBody) ballBody.setVelocity(0);
    ballVZ = 0;
    ballZ = 0;
    isJumping = false;
    playerZ = 0;
    if (jumpTween) {
        jumpTween.stop();
        jumpTween = null;
    }
    if (playerPoseEvent) {
        playerPoseEvent.remove(false);
        playerPoseEvent = null;
    }
    resetVirtualInput();
    resetPlayerFormation(nextServer === 'PLAYER');
    resetOpponentFormation();
    setControlledRole('RECEPTOR');
    if (trail) trail.stop();
    landingShadowState = 0;
    if (landingShadow) landingShadow.setVisible(false);
    if (scene) {
        const delay = pointResult.matchWinner ? 2600 : 1800;
        scene.time.delayedCall(delay, async () => {
            if (pointResult.matchWinner) {
                const saveResult = await saveResultPromise;
                const playerWon = pointResult.matchWinner === 'PLAYER';
                const saveError = saveResult && saveResult.error;
                resetMatchScore();
                resetServe();
                if (saveError) {
                    openCampaignModal(`Resultado pendente: ${saveError.message || 'tente novamente.'}`);
                } else if (playerWon && campaignState.campaignCompleted) {
                    openCampaignModal('Campanha concluída! Você venceu todas as seleções.');
                } else if (playerWon) {
                    openCampaignModal(`Vitória salva. Próximo desafio: ${getCurrentOpponent().name}.`);
                } else {
                    openCampaignModal(`Derrota salva. Tente novamente contra ${getCurrentOpponent().name}.`);
                }
                return;
            }

            if (pointResult.setWinner) resetSetScore();
            resetServe();
        });
    }
}

function placeBallAboveHead(visible) {
    ballBody.setVelocity(0);
    ballBody.x = player.x;
    ballBody.y = getHeadBallGroundY();
    ballZ = 0;
    ballVZ = 0;
    ball.setVisible(visible);
    ballShadow.setVisible(visible);
    projectBall();
}

function getHeadBallGroundY() {
    return player.y - PLAYER.headOffsetY - PLAYER.ballAboveHead;
}

function projectBall() {
    const zRatio = Phaser.Math.Clamp(ballZ / BALL.tossTargetZ, 0, 1);
    const lift = ballZ * BALL.zToPixels;

    ball.x = ballBody.x;
    ball.y = ballBody.y - lift;
    ball.setScale(1 + zRatio * 0.12);

    ballShadow.x = ballBody.x;
    ballShadow.y = ballBody.y + 10;
    ballShadow.setScale(1 - zRatio * 0.35, 1 - zRatio * 0.35);
}

function calculateServeSideVelocity() {
    const offset = Phaser.Math.Clamp((ballBody.x - COURT.centerX) / 220, -1, 1);
    // Invertido: se estiver na direita (offset positivo), vai para a esquerda (vx negativo)
    return -offset * BALL.serveSideVelocity;
}

function screenYToDepth(y) {
    return Math.round(y - NET.y);
}

function screenXToWidth(x) {
    return Math.round(x - COURT.centerX);
}

function performServeHitInstant(scene, power) {
    // Serve has its own power model: timing/height can make it stronger than a rally attack.
    const ratio = power.label === 'RUIM' ? 0.25 : power.label === 'MEDIO' ? 0.6 : 0.95;
    const servePowerRatio = Phaser.Math.Clamp(Math.max(hitPowerRatio, ratio), 0.25, 1);

    state = 'FLYING';
    debugShot.hit = {
        x: screenXToWidth(ballBody.x),
        y: screenYToDepth(ballBody.y),
        z: Math.round(ballZ),
        quality: power.label
    };
    ball.clearTint();
    setPlayerPose(scene, 'playerHit', 420);
    doPlayerJump(scene);
    beginShotLog('SAQUE', 'JOGADOR', {
        force: servePowerRatio.toFixed(2),
        quality: power.label
    });

    // -------- Distance model (force -> target landing point) --------
    // Choose a desired landing Y on opponent side from ratio:
    // - weak lands near net
    // - ideal lands mid-court
    // - strong lands deep; at very high charge it can go out.
    const y0 = ballBody.y;
    const x0 = ballBody.x;
    const tToGround = estimateTimeToGround(ballZ, power.up, BALL.gravityZFlightPreNet);

    // Fallback if something weird happens.
    const flightT = Number.isFinite(tToGround) && tToGround > 0 ? tToGround : 0.75;

    // Formula adjusted to compensate for gravity drop post-net.
    // Serve lands deeper and faster than rally attacks when timing is good.
    const targetDepth = Phaser.Math.Linear(-45, -275, servePowerRatio);
    const qualityOffset = power.label === 'RUIM' ? 58 : power.label === 'MEDIO' ? 22 : 0;
    const overshootRisk = servePowerRatio > 0.97 && power.label === 'BOM'
        ? Phaser.Math.Linear(0, -34, (servePowerRatio - 0.97) / 0.03)
        : 0;
    const finalDepth = targetDepth + qualityOffset + overshootRisk;

    const targetY = NET.y + finalDepth;

    const targetX = x0 + calculateServeSideVelocity() * 0.55;
    const vx = (targetX - x0) / flightT;
    const vy = (targetY - y0) / flightT;

    // Marca início do rali após sacar
    gamePhase = 'RALLY';
    lastTouch = 'PLAYER_HIT';
    lastPlayerShotKind = 'SERVE';
    lastPlayerAttackPower = servePowerRatio;
    opponentReceiveAttemptedForPlayerHit = false;
    opponentDefenseStartedAtMs = 0;
    opponentLowServeDecision = null;
    resetOpponentLetOutDecision();

    // Saque usa velocidade propria, mais forte que o ataque comum.
    const speedMult = Phaser.Math.Linear(0.84, 0.94, servePowerRatio);
    ballBody.setVelocity(
        Phaser.Math.Clamp(vx * speedMult, -PHYS_CLAMP.maxVX, PHYS_CLAMP.maxVX),
        Phaser.Math.Clamp(vy * speedMult, -PHYS_CLAMP.maxVY, PHYS_CLAMP.maxVY)
    );
    const finalVX = ballBody.body.velocity.x;
    const finalVY = ballBody.body.velocity.y;

    // -------- Arc model (ensure clearance at the net) --------
    // Start with a baseline up impulse from bucket, then make sure Z at net crossing
    // is above the net height + clearance.
    ballVZ = Phaser.Math.Clamp(power.up + (20 * servePowerRatio), PHYS_CLAMP.minVZ, PHYS_CLAMP.maxVZ);
    const tToNet = (NET.y - y0) / vy; // vy is negative when going to opponent
    const initialVZ = ballVZ;
    let zAtNetBeforeAssist = '---';
    let netAssistApplied = 0;
    if (Number.isFinite(tToNet) && tToNet > 0) {
        const az = BALL.gravityZFlightPreNet;
        const zAtNet = ballZ + ballVZ * tToNet + 0.5 * az * tToNet * tToNet;
        zAtNetBeforeAssist = Math.round(zAtNet);
        const needed = NET.heightZ + HIT_TRAJECTORY.netClearanceZ;
        if (zAtNet < needed) {
            // Solve for extra VZ to reach needed at tToNet: needed = z0 + (vz+dvz)*t + 0.5*az*t^2
            const dvz = (needed - (ballZ + ballVZ * tToNet + 0.5 * az * tToNet * tToNet)) / tToNet;
            netAssistApplied = Phaser.Math.Clamp(dvz, 0, power.netAssist);
            ballVZ = Phaser.Math.Clamp(ballVZ + netAssistApplied, PHYS_CLAMP.minVZ, PHYS_CLAMP.maxVZ);
        }
    }
    setShotCalculations({
        'forca carregada': hitPowerRatio.toFixed(3),
        'forca final saque': servePowerRatio.toFixed(3),
        'qualidade saque': power.label,
        'profundidade base': `${targetDepth.toFixed(1)} = linear(-45, -275, ${servePowerRatio.toFixed(3)})`,
        'ajuste qualidade': qualityOffset.toFixed(1),
        'risco fora': overshootRisk.toFixed(1),
        'profundidade alvo': finalDepth.toFixed(1),
        'limites quadra adversaria': `${OPPONENT_COURT.minY - NET.y} ate ${OPPONENT_COURT.maxY - NET.y}`,
        'origem': `x ${Math.round(x0)} y ${Math.round(y0)} z ${Math.round(ballZ)}`,
        'alvo': `x ${Math.round(targetX)} y ${Math.round(targetY)}`,
        'tempo ate chao': `${flightT.toFixed(3)}s`,
        'velocidade bruta': `vx ${Math.round(vx)} vy ${Math.round(vy)}`,
        'multiplicador velocidade': speedMult.toFixed(2),
        'velocidade aplicada': `vx ${Math.round(finalVX)} vy ${Math.round(finalVY)} vz ${Math.round(ballVZ)}`,
        'velocidade total aplicada': Math.round(Math.hypot(finalVX, finalVY, ballVZ)),
        'gravidade voo': BALL.gravityZFlightPreNet,
        'vz inicial': Math.round(initialVZ),
        'tempo ate rede': Number.isFinite(tToNet) ? `${tToNet.toFixed(3)}s` : '---',
        'z na rede antes ajuste': zAtNetBeforeAssist,
        'assistencia rede': Math.round(netAssistApplied)
    });
    setShotVelocity(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ);

    trail.start();
    addHitEffect(scene, power);
}

function doPlayerJump(scene) {
    if (jumpTween) {
        jumpTween.stop();
        jumpTween = null;
    }

    const jumper = getControlledPlayer();
    const startY = jumper.y;
    const jumpUp = 28;
    jumpTween = scene.tweens.add({
        targets: jumper,
        y: startY - jumpUp,
        duration: 110,
        ease: 'Quad.out',
        yoyo: true,
        hold: 80,
        onComplete: () => {
            jumper.y = Phaser.Math.Clamp(jumper.y, COURT.minY, COURT.maxY);
            jumpTween = null;
        }
    });
}

function getAttackQuality(ballZNow, windowZ) {
    // When the ball is falling and near `hitWindowZ`, timing is best.
    // Smaller |ballZ - windowZ| -> better.
    const d = Math.abs(ballZNow - windowZ);
    if (d <= ATTACK.goodWindowZ) return ATTACK.good;
    if (d <= ATTACK.midWindowZ) return ATTACK.mid;
    return ATTACK.weak;
}

function resetServe() {
    cancelOpponentServeEvents();
    pointLocked = false;
    state = 'READY';
    gamePhase = 'SERVE';
    queuedServeHitAt = 0;
    lastTouch = 'NONE';
    desiredAttackRole = 'RIGHT';
    lastPlayerAttackPower = 0;
    lastPlayerShotKind = 'ATTACK';
    opponentReceiveAttemptedForPlayerHit = false;
    opponentDefenseStartedAtMs = 0;
    opponentLowServeDecision = null;
    resetOpponentLetOutDecision();
    resetVirtualInput();
    resetPlayerFormation(nextServer === 'PLAYER');
    resetOpponentFormation();
    setControlledRole('RECEPTOR');
    ballZ = 0;
    ballVZ = 0;
    ball.clearTint();
    ball.setVisible(false);
    ballShadow.setVisible(false);
    trail.stop();
    ballBody.setVelocity(0);
    liftChargeStart = 0;
    rollDirY = 0;
    rollEndY = 0;
    smoothedLandingX = ballBody.x;
    smoothedLandingY = ballBody.y;
    landingCloseToGround = false;
    if (jumpTween) {
        jumpTween.stop();
        jumpTween = null;
    }
    isJumping = false;
    playerZ = 0;
    if (playerPoseEvent) {
        playerPoseEvent.remove(false);
        playerPoseEvent = null;
    }
    getControlledPlayer().setTexture('playerBack');
    if (!difficultySelected) return;

    showStatusMessage(gameScene, 'SAQUE', 900);
    startRallyLog(nextServer);

    if (nextServer === 'OPPONENT') {
        startOpponentServe(gameScene);
    }
}

function resetVirtualInput() {
    virtualInput = {
        left: false,
        right: false,
        up: false,
        down: false,
        liftDown: false,
        liftPressed: false,
        liftReleased: false,
        receiveDown: false,
        jumpPressed: false,
        actionPressed: false
    };
}

function resetPlayerFormation(useServePosition = false) {
    if (!playerBacker || !playerLeft || !playerRight || !playerSetter) return;

    const receptorHome = useServePosition ? PLAYER_HOME.SERVER : PLAYER_HOME.RECEPTOR;
    playerBacker.x = receptorHome.x;
    playerBacker.y = receptorHome.y;
    playerLeft.x = PLAYER_HOME.LEFT.x;
    playerLeft.y = PLAYER_HOME.LEFT.y;
    playerRight.x = PLAYER_HOME.RIGHT.x;
    playerRight.y = PLAYER_HOME.RIGHT.y;
    playerSetter.x = PLAYER_HOME.SETTER.x;
    playerSetter.y = PLAYER_HOME.SETTER.y;

    playerTeam.forEach((p) => {
        if (!p) return;
        p.setTexture('playerBack');
        p.setDisplayOrigin(p.width * 0.5, p.height);
        p.body?.setVelocity?.(0);
    });
    playerSetter.setTexture('playerBack');
}

function resetOpponentFormation() {
    if (!opponentBack || !opponentFront || !oppLeft || !oppRight) return;

    opponentBack.x = OPPONENT_AI.backHome.x;
    opponentBack.y = OPPONENT_AI.backHome.y;
    opponentFront.x = OPPONENT_AI.setterHome.x;
    opponentFront.y = OPPONENT_AI.setterHome.y;
    oppLeft.x = OPPONENT_AI.leftHome.x;
    oppLeft.y = OPPONENT_AI.leftHome.y;
    oppRight.x = OPPONENT_AI.rightHome.x;
    oppRight.y = OPPONENT_AI.rightHome.y;

    [opponentBack, opponentFront, oppLeft, oppRight].forEach((p) => {
        if (!p) return;
        p.setTexture('oppParado');
        p.setOrigin(0.5, 1);
    });
    opponentAttacker = oppLeft;
}

function cancelOpponentServeEvents() {
    if (opponentServeLiftEvent) {
        opponentServeLiftEvent.remove(false);
        opponentServeLiftEvent = null;
    }
    if (opponentServeHitEvent) {
        opponentServeHitEvent.remove(false);
        opponentServeHitEvent = null;
    }
    if (opponentPassToSetterEvent) {
        opponentPassToSetterEvent.remove(false);
        opponentPassToSetterEvent = null;
    }
    if (opponentSetAttackEvent) {
        opponentSetAttackEvent.remove(false);
        opponentSetAttackEvent = null;
    }
}

function startOpponentServe(scene) {
    if (!scene || !opponentBack || !ballBody || !ball) return;

    state = 'OPP_SERVE_WAIT';
    gamePhase = 'SERVE';
    lastTouch = 'NONE';
    setControlledRole('RECEPTOR');

    const server = opponentBack;
    server.x = OPPONENT_AI.serverHome.x;
    server.y = OPPONENT_AI.serverHome.y;
    server.setTexture('oppLevantador');

    ball.clearTint();
    ballBody.setVelocity(0);
    ballBody.x = server.x;
    ballBody.y = server.y + 8;
    ballZ = 0;
    ballVZ = 0;
    ball.setVisible(true);
    ballShadow.setVisible(true);
    projectBall();

    opponentServeLiftEvent = scene.time.delayedCall(650, () => {
        if (state !== 'OPP_SERVE_WAIT') return;
        server.setTexture('oppLevantador');
        ballZ = 0;
        ballVZ = 360;
        state = 'OPP_SERVE_TOSS';
        opponentServeLiftEvent = null;
    });

    opponentServeHitEvent = scene.time.delayedCall(1250, () => {
        if (state !== 'OPP_SERVE_TOSS') return;
        performOpponentServeHit(scene, server);
        opponentServeHitEvent = null;
    });
}

function performOpponentServeHit(scene, server) {
    const difficulty = getDifficultyProfile();
    const serveTuning = difficulty.serve;
    state = 'FLYING';
    gamePhase = 'RALLY';
    lastTouch = 'OPP_HIT';
    ball.clearTint();
    beginShotLog('SAQUE', 'ADVERSARIO', {
        force: difficulty.label,
        quality: currentDifficultyKey === 'advanced' ? 'forte' : currentDifficultyKey === 'easy' ? 'leve' : 'normal'
    });

    const startX = ballBody.x;
    const startY = ballBody.y;
    const targetX = Phaser.Math.Clamp(
        playerBacker.x + Phaser.Math.Between(-serveTuning.spreadX, serveTuning.spreadX),
        COURT.minX + 30,
        COURT.maxX - 30
    );
    const targetY = NET.y + Phaser.Math.Between(serveTuning.minDepth, serveTuning.maxDepth);
    const flightTime = serveTuning.flightTime;
    const vx = (targetX - startX) / flightTime;
    const vy = (targetY - startY) / flightTime;
    const attackGravityZ = getFlightGravityAtY(startY);

    ballZ = Math.max(ballZ, serveTuning.minBallZ);
    ballVZ = calculateLaunchVZForFlight(ballZ, 0, startY, vy, flightTime);
    ballBody.setVelocity(
        Phaser.Math.Clamp(vx, -420, 420),
        Phaser.Math.Clamp(vy, 100, 680)
    );
    setShotCalculations({
        'modelo': 'saque automatico IA',
        'dificuldade IA': difficulty.label,
        'forca saque': currentDifficultyKey === 'advanced' ? 'FORTE' : currentDifficultyKey === 'easy' ? 'LEVE' : 'NORMAL',
        'origem': `x ${Math.round(startX)} y ${Math.round(startY)} z ${Math.round(ballZ)}`,
        'alvo': `x ${Math.round(targetX)} y ${Math.round(targetY)}`,
        'tempo voo': `${flightTime.toFixed(3)}s`,
        'formula vx': `(${Math.round(targetX)} - ${Math.round(startX)}) / ${flightTime.toFixed(3)} = ${Math.round(vx)}`,
        'formula vy': `(${Math.round(targetY)} - ${Math.round(startY)}) / ${flightTime.toFixed(3)} = ${Math.round(vy)}`,
        'velocidade aplicada': `vx ${Math.round(ballBody.body.velocity.x)} vy ${Math.round(ballBody.body.velocity.y)} vz ${Math.round(ballVZ)}`,
        'velocidade total aplicada': Math.round(Math.hypot(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ)),
        'gravidade voo': attackGravityZ,
        'formula vz': `integracao da gravidade variavel por ${flightTime.toFixed(3)}s`
    });
    setShotVelocity(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ);

    server.setTexture('oppAtaque');
    scene.time.delayedCall(520, () => {
        if (server.texture && server.texture.key === 'oppAtaque') server.setTexture('oppParado');
    });
    scene.cameras.main.shake(160, 0.012);
    addHitEffect(scene, { shake: 0.01 });
    trail.start();
}

function setPlayerPose(scene, texture, duration) {
    if (playerPoseEvent) {
        playerPoseEvent.remove(false);
        playerPoseEvent = null;
    }

    player.setTexture(texture);
    playerPoseEvent = scene.time.delayedCall(duration, () => {
        player.setTexture('playerBack');
        playerPoseEvent = null;
    });
}

function addPoint(playerScored) {
    const side = playerScored ? 'player' : 'opponent';
    score[side] += 1;

    let setWinner = null;
    let matchWinner = null;
    if (hasWonCurrentSet(playerScored)) {
        if (playerScored) score.playerSets += 1;
        else score.opponentSets += 1;
        setWinner = playerScored ? 'PLAYER' : 'OPPONENT';

        if (score.playerSets >= MATCH.setsToWinMatch || score.opponentSets >= MATCH.setsToWinMatch) {
            matchWinner = score.playerSets > score.opponentSets ? 'PLAYER' : 'OPPONENT';
        }
    }

    updateScoreboard();
    return { setWinner, matchWinner };
}

function getCurrentSetTargetPoints() {
    const playedSets = score.playerSets + score.opponentSets;
    return playedSets >= 4 ? MATCH.tieBreakPoints : MATCH.regularSetPoints;
}

function hasWonCurrentSet(playerScored) {
    const sidePoints = playerScored ? score.player : score.opponent;
    const otherPoints = playerScored ? score.opponent : score.player;
    return sidePoints >= getCurrentSetTargetPoints() && sidePoints - otherPoints >= MATCH.minPointLead;
}

function resetSetScore() {
    score.player = 0;
    score.opponent = 0;
    updateScoreboard();
}

function resetMatchScore() {
    score.player = 0;
    score.opponent = 0;
    score.playerSets = 0;
    score.opponentSets = 0;
    updateScoreboard();
}

function updateScoreboard() {
    const playerScore = document.getElementById('player-score');
    const opponentScore = document.getElementById('opponent-score');
    const playerSets = document.getElementById('player-sets');
    const opponentSets = document.getElementById('opponent-sets');
    if (playerScore) playerScore.innerText = String(score.player);
    if (opponentScore) opponentScore.innerText = String(score.opponent);
    if (playerSets) playerSets.innerText = String(score.playerSets);
    if (opponentSets) opponentSets.innerText = String(score.opponentSets);
}

function addHitEffect(scene, power) {
    scene.cameras.main.shake(120, power.shake);

    const ring = scene.add.circle(ball.x, ball.y, 10, 0xffffff, 0);
    ring.setStrokeStyle(4, 0xffffff, 0.9);
    scene.tweens.add({
        targets: ring,
        scale: 3,
        alpha: 0,
        duration: 220,
        onComplete: () => ring.destroy()
    });
}

function createTrajectoryOverlay(scene) {
    trajectoryGraphics = scene.add.graphics();
    trajectoryGraphics.setDepth(13);
}

function clearTrajectoryOverlay() {
    trajectoryPoints = [];
    trajectoryNetMarkers = [];
    lastTrajectoryRecordMs = 0;
    debugShot = {
        maxZ: 0,
        hit: null,
        net: null,
        landing: null
    };
    hasRecordedNetCrossing = false;
    closestNetCrossing = null;
    if (trajectoryGraphics) trajectoryGraphics.clear();
    trajectoryLabels.forEach((label) => label.destroy());
    trajectoryLabels = [];
}

function updateTrajectoryOverlay(scene) {
    if (!trajectoryGraphics) return;

    const active =
        state === 'TOSS' ||
        state === 'HIT_WINDOW' ||
        state === 'FLYING' ||
        state === 'BOUNCE_ROLL' ||
        state === 'NET_HIT';

    if (!active || !ball.visible) return;

    const now = scene.time.now;
    const last = trajectoryPoints[trajectoryPoints.length - 1];
    const dist = last ? Phaser.Math.Distance.Between(ball.x, ball.y, last.x, last.y) : Infinity;
    if (now - lastTrajectoryRecordMs < 28 && dist < 8) return;

    trajectoryPoints.push({ x: ball.x, y: ball.y, z: ballZ });
    lastTrajectoryRecordMs = now;
    if (trajectoryPoints.length > 180) trajectoryPoints.shift();

    drawTrajectoryOverlay();
}

function drawTrajectoryOverlay() {
    trajectoryGraphics.clear();
    if (trajectoryPoints.length >= 2) {
        trajectoryGraphics.lineStyle(3, 0xffffff, 0.8);
        trajectoryGraphics.beginPath();
        trajectoryGraphics.moveTo(trajectoryPoints[0].x, trajectoryPoints[0].y);
        for (let i = 1; i < trajectoryPoints.length; i++) {
            trajectoryGraphics.lineTo(trajectoryPoints[i].x, trajectoryPoints[i].y);
        }
        trajectoryGraphics.strokePath();

        trajectoryGraphics.fillStyle(0xffffff, 0.85);
        for (let i = 0; i < trajectoryPoints.length; i += 10) {
            trajectoryGraphics.fillCircle(trajectoryPoints[i].x, trajectoryPoints[i].y, 2.5);
        }
    }

    for (const marker of trajectoryNetMarkers) {
        const color = marker.cleared ? 0x22ff88 : 0xff2b2b;
        trajectoryGraphics.lineStyle(2, color, 0.95);
        trajectoryGraphics.lineBetween(marker.x, NET.y, marker.x, marker.y);
        trajectoryGraphics.fillStyle(color, 0.95);
        trajectoryGraphics.fillCircle(marker.x, marker.y, 6);
    }
}

function addNetHeightMarker(scene, x, zAtNet, cleared) {
    recordShotNet(zAtNet, cleared);

    if (!SHOW_TRAJECTORY) {
        debugShot.net = {
            x: screenXToWidth(x),
            y: 0,
            z: Math.round(zAtNet),
            cleared
        };
        return;
    }

    const markerY = NET.y - zAtNet * BALL.zToPixels;
    trajectoryNetMarkers.push({ x, y: markerY, z: zAtNet, cleared });
    debugShot.net = {
        x: screenXToWidth(x),
        y: 0,
        z: Math.round(zAtNet),
        cleared
    };
    drawTrajectoryOverlay();

    const label = scene.add.text(x + 10, markerY - 12, `Z rede ${Math.round(zAtNet)}`, {
        fontFamily: 'Consolas, monospace',
        fontSize: '13px',
        color: cleared ? '#7dffb0' : '#ff8a8a',
        backgroundColor: 'rgba(0,0,0,0.5)',
        padding: { x: 5, y: 3 }
    });
    label.setDepth(18);
    trajectoryLabels.push(label);
}

function createDevOverlay(scene) {
    devGraphics = scene.add.graphics();
    devGraphics.setDepth(4);
    devText = scene.add.text(12, 12, '', {
        fontFamily: 'Consolas, monospace',
        fontSize: '13px',
        color: '#ffffff',
        backgroundColor: 'rgba(0,0,0,0.45)',
        padding: { x: 8, y: 6 }
    });
    devText.setDepth(17);
}

function clearDevLabels() {
    devLabels.forEach((label) => label.destroy());
    devLabels = [];
    devPositionLabels.forEach((label) => label.destroy());
    devPositionLabels = [];
}

function createChargeBar(scene) {
    const barWidth = 260;
    const barHeight = 14;
    const x = COURT.centerX - barWidth / 2;
    const y = 560;

    chargeBarBg = scene.add.rectangle(x + barWidth / 2, y, barWidth, barHeight, 0x000000, 0.45);
    chargeBarBg.setStrokeStyle(2, 0xffffff, 0.25);
    chargeBarBg.setDepth(30);
    chargeBarBg.setVisible(false);

    chargeBarFill = scene.add.rectangle(x, y, 0, barHeight - 4, 0xffd24a, 0.9);
    chargeBarFill.setOrigin(0, 0.5);
    chargeBarFill.setDepth(31);
    chargeBarFill.setVisible(false);

    chargeBarText = scene.add.text(COURT.centerX, y - 24, '', {
        fontFamily: 'Outfit',
        fontSize: '16px',
        fontStyle: 'italic',
        fontWeight: '700',
        color: '#ffffff',
        stroke: '#000000',
        strokeThickness: 4
    });
    chargeBarText.setOrigin(0.5);
    chargeBarText.setDepth(31);
    chargeBarText.setVisible(false);
}

function createStatusText(scene) {
    statusText = scene.add.text(COURT.centerX, 112, '', {
        fontFamily: 'Outfit',
        fontSize: '42px',
        fontStyle: 'italic',
        fontWeight: '700',
        color: '#ffffff',
        stroke: '#07111f',
        strokeThickness: 8
    });
    statusText.setOrigin(0.5);
    statusText.setDepth(40);
    statusText.setVisible(false);
}

function createTouchControls(scene) {
    if (!scene.sys.game.device.input.touch) return;

    document.querySelectorAll('.mobile-touch-controls').forEach((element) => element.remove());

    const wrapper = document.getElementById('game-wrapper') || document.querySelector('.game-wrapper');
    const header = document.querySelector('header');
    if (!wrapper || !header) return;

    const dpad = createVirtualJoystick();

    const actionPad = document.createElement('div');
    actionPad.className = 'mobile-touch-controls mobile-action-pad';
    [
        { action: 'jump', label: 'P', down: () => pressVirtualButton('jump') },
        { action: 'lift', label: 'S', down: () => setVirtualButton('lift', true), up: () => setVirtualButton('lift', false) },
        { action: 'receive', label: 'M', down: () => setVirtualReceive(scene, true), up: () => setVirtualReceive(scene, false) },
        { action: 'action', label: 'A', down: () => pressVirtualButton('action') }
    ].forEach((control) => {
        const button = createDomTouchButton(control.label, 'mobile-action-btn');
        button.dataset.action = control.action;
        bindVirtualTouch(button, control.down, control.up);
        actionPad.appendChild(button);
    });

    wrapper.insertBefore(dpad, document.getElementById('game-container'));
    header.appendChild(actionPad);
    touchControls.push(dpad, actionPad);
}

function createVirtualJoystick() {
    const joystick = document.createElement('div');
    joystick.className = 'mobile-touch-controls mobile-joystick';
    joystick.setAttribute('aria-label', 'Joystick de movimento');

    const base = document.createElement('div');
    base.className = 'mobile-joystick-base';

    const knob = document.createElement('div');
    knob.className = 'mobile-joystick-knob';

    joystick.append(base, knob);

    const maxDistance = 42;
    const deadZone = 10;

    const update = (event) => {
        const rect = joystick.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const rawX = event.clientX - centerX;
        const rawY = event.clientY - centerY;
        const distance = Math.min(maxDistance, Math.hypot(rawX, rawY));
        const angle = Math.atan2(rawY, rawX);
        const x = Math.cos(angle) * distance;
        const y = Math.sin(angle) * distance;

        knob.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;

        setVirtualDirection('left', x < -deadZone);
        setVirtualDirection('right', x > deadZone);
        setVirtualDirection('up', y < -deadZone);
        setVirtualDirection('down', y > deadZone);
    };

    const release = () => {
        joystick.classList.remove('is-active');
        knob.style.transform = 'translate(-50%, -50%)';
        clearVirtualDirections();
    };

    joystick.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        joystick.setPointerCapture?.(event.pointerId);
        joystick.classList.add('is-active');
        update(event);
    });
    joystick.addEventListener('pointermove', (event) => {
        if (!joystick.classList.contains('is-active')) return;
        event.preventDefault();
        update(event);
    });
    joystick.addEventListener('pointerup', (event) => {
        event.preventDefault();
        release();
    });
    joystick.addEventListener('pointercancel', release);
    joystick.addEventListener('lostpointercapture', release);
    joystick.addEventListener('contextmenu', (event) => event.preventDefault());

    return joystick;
}

function createDomTouchButton(label, className) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = className;
    button.textContent = label;
    return button;
}

function bindVirtualTouch(button, down, up) {
    const release = () => {
        button.classList.remove('is-pressed');
        if (up) up();
    };

    button.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        button.setPointerCapture?.(event.pointerId);
        button.classList.add('is-pressed');
        if (down) down();
    });
    button.addEventListener('pointerup', (event) => {
        event.preventDefault();
        release();
    });
    button.addEventListener('pointercancel', release);
    button.addEventListener('lostpointercapture', release);
    button.addEventListener('contextmenu', (event) => event.preventDefault());
}

function setVirtualDirection(direction, down) {
    virtualInput[direction] = down;
}

function clearVirtualDirections() {
    ['left', 'right', 'up', 'down'].forEach((direction) => setVirtualDirection(direction, false));
}

function pressVirtualButton(action) {
    virtualInput[`${action}Pressed`] = true;
}

function setVirtualReceive(scene, down) {
    virtualInput.receiveDown = down;
    const receiver = getControlledPlayer();
    if (!receiver) return;

    if (down) {
        const tex = scene.textures.exists('playerMachete') ? 'playerMachete' : 'playerBack';
        receiver.setTexture(tex);
        return;
    }

    if (receiver.texture && receiver.texture.key === 'playerMachete') {
        receiver.setTexture('playerBack');
    }
}

function setVirtualButton(action, down) {
    const downProp = `${action}Down`;
    const pressedProp = `${action}Pressed`;
    const releasedProp = `${action}Released`;

    if (down && !virtualInput[downProp]) {
        virtualInput[pressedProp] = true;
    }
    if (!down && virtualInput[downProp]) {
        virtualInput[releasedProp] = true;
    }
    virtualInput[downProp] = down;
}

function showStatusMessage(scene, text, duration = 900, color = '#ffffff') {
    if (!scene || !statusText) return;
    if (statusTextEvent) {
        statusTextEvent.remove(false);
        statusTextEvent = null;
    }

    statusText.setText(text);
    statusText.setStyle({ color });
    statusText.setAlpha(1);
    statusText.setScale(0.92);
    statusText.setVisible(true);

    scene.tweens.add({
        targets: statusText,
        scale: 1,
        duration: 120,
        ease: 'Quad.out'
    });

    statusTextEvent = scene.time.delayedCall(duration, () => {
        if (!statusText) return;
        scene.tweens.add({
            targets: statusText,
            alpha: 0,
            duration: 180,
            onComplete: () => {
                statusText.setVisible(false);
                statusText.setAlpha(1);
            }
        });
        statusTextEvent = null;
    });
}

function createCourtZoneOverlay(scene) {
    courtZoneGraphics = scene.add.graphics();
    courtZoneGraphics.setDepth(5.8);
    drawCourtZoneOverlay();
}

function drawCourtZoneOverlay() {
    if (!courtZoneGraphics) return;
    courtZoneGraphics.clear();

    const zones = [
        { zone: OPPONENT_AI.backHome.zone, color: 0xff1f2f },
        { zone: OPPONENT_AI.leftHome.zone, color: 0xff1f2f },
        { zone: OPPONENT_AI.rightHome.zone, color: 0xff1f2f },
        { zone: { minX: COURT.minX, maxX: COURT.centerX, minY: NET.y, maxY: COURT.maxY }, color: 0xff1f2f },
        { zone: { minX: COURT.centerX, maxX: COURT.maxX, minY: NET.y, maxY: COURT.maxY }, color: 0xff1f2f }
    ];

    zones.forEach(({ zone, color }) => {
        const width = zone.maxX - zone.minX;
        const height = zone.maxY - zone.minY;
        courtZoneGraphics.fillStyle(color, 0.025);
        courtZoneGraphics.fillRect(zone.minX, zone.minY, width, height);
        courtZoneGraphics.lineStyle(5, color, 0.82);
        courtZoneGraphics.strokeRect(zone.minX, zone.minY, width, height);
    });

    courtZoneGraphics.lineStyle(6, 0xff1f2f, 0.9);
    courtZoneGraphics.lineBetween(COURT.centerX, OPPONENT_COURT.minY, COURT.centerX, COURT.maxY);
    courtZoneGraphics.lineStyle(6, 0xff1f2f, 0.9);
    courtZoneGraphics.lineBetween(COURT.minX, NET.y, COURT.maxX, NET.y);
    courtZoneGraphics.lineStyle(5, 0xff1f2f, 0.72);
    courtZoneGraphics.lineBetween(COURT.minX, OPPONENT_AI.zoneSplitY, COURT.maxX, OPPONENT_AI.zoneSplitY);
}

function createPlayerRadiusOverlay(scene) {
    playerRadiusGraphics = scene.add.graphics();
    playerRadiusGraphics.setDepth(6.5);
}

function updatePlayerRadiusOverlay() {
    if (!playerRadiusGraphics) return;
    playerRadiusGraphics.clear();

    const controlled = getControlledPlayer();
    const opponentReceiveRadius = getDifficultyProfile().receiveRadius;
    const items = [
        { sprite: playerBacker, radius: RECEIVE.radius, color: 0x22ff88, alpha: playerBacker === controlled ? 0.78 : 0.42 },
        { sprite: playerLeft, radius: RECEIVE.radius, color: 0x22ff88, alpha: playerLeft === controlled ? 0.78 : 0.42 },
        { sprite: playerRight, radius: RECEIVE.radius, color: 0x22ff88, alpha: playerRight === controlled ? 0.78 : 0.42 },
        { sprite: opponentBack, radius: opponentReceiveRadius, color: 0xff5f6d, alpha: 0.36 },
        { sprite: oppLeft, radius: opponentReceiveRadius, color: 0xff5f6d, alpha: 0.36 },
        { sprite: oppRight, radius: opponentReceiveRadius, color: 0xff5f6d, alpha: 0.36 }
    ];

    items.forEach(({ sprite, radius, color, alpha }) => {
        if (!sprite || !sprite.visible) return;
        playerRadiusGraphics.fillStyle(color, alpha * 0.08);
        playerRadiusGraphics.fillCircle(sprite.x, sprite.y, radius);
        playerRadiusGraphics.lineStyle(2, color, alpha);
        playerRadiusGraphics.strokeCircle(sprite.x, sprite.y, radius);
    });
}

function updateChargeBar(scene) {
    const charging = state === 'CHARGING_LIFT';
    if (!chargeBarBg || !chargeBarFill || !chargeBarText) return;

    if (!charging) {
        chargeBarBg.setVisible(false);
        chargeBarFill.setVisible(false);
        chargeBarText.setVisible(false);
        return;
    }

    const chargeMs = Math.min(scene.time.now - liftChargeStart, LIFT_CHARGE.maxMs);
    const ratio = Phaser.Math.Clamp(chargeMs / LIFT_CHARGE.maxMs, 0, 1);
    const barWidth = 260;
    const fillWidth = Math.max(6, Math.round(barWidth * ratio));

    const tint = ratio < 0.33 ? 0xff8a00 : ratio < 0.72 ? 0xffd24a : 0x22ff88;

    chargeBarBg.setVisible(true);
    chargeBarFill.setVisible(true);
    chargeBarText.setVisible(true);

    chargeBarFill.width = fillWidth;
    chargeBarFill.fillColor = tint;
    chargeBarText.setText(`FORÇA DE ATAQUE`);
}

function updateDevOverlay(scene) {
    if (!SHOW_DEBUG) {
        if (devText) devText.setVisible(false);
        if (devGraphics) devGraphics.clear();
        clearDevLabels();
        return;
    }
    devGraphics.clear();
    clearDevLabels();

    drawCourtAxis();
    drawHeightRuler();
    drawNetMarker();
    drawPlayersDebug();
    drawBallAnchorDebug();
    drawLandingDebug(scene);

    const x = Math.round(ballBody.x - COURT.centerX);
    const depth = Math.round(ballBody.y - NET.y);
    const z = Math.round(ballZ);
    const visualY = Math.round(ball.y);
    const liftMs = state === 'CHARGING_LIFT' ? Math.min(LIFT_CHARGE.maxMs, Math.round(scene.time.now - liftChargeStart)) : 0;
    const liftRatio = liftMs > 0 ? Math.min(1, liftMs / LIFT_CHARGE.maxMs) : 0;
    const hitText = debugShot.hit
        ? `Z ${debugShot.hit.z} (${debugShot.hit.quality})`
        : '---';
    const netText = debugShot.net
        ? `Y 0 | Z ${debugShot.net.z} ${debugShot.net.cleared ? 'PASSOU' : 'REDE'}`
        : closestNetCrossing
            ? `aprox Y0 | Z ${Math.round(closestNetCrossing.z)} (dist ${Math.round(closestNetCrossing.depthAbs)})`
            : '---';
    const landingText = debugShot.landing
        ? `X ${debugShot.landing.x} | Y ${debugShot.landing.y}`
        : '---';
    devText.setVisible(false);
}

function drawLandingDebug(scene) {
    if (!lastLandingMark) return;
    const age = scene.time.now - lastLandingMark.t;
    if (age > 2200) return;
    const a = Phaser.Math.Clamp(1 - age / 2200, 0, 1);
    devGraphics.lineStyle(3, 0xffd24a, 0.95 * a);
    devGraphics.strokeCircle(lastLandingMark.x, lastLandingMark.y, 12);
    devGraphics.lineBetween(lastLandingMark.x - 14, lastLandingMark.y, lastLandingMark.x + 14, lastLandingMark.y);
    devGraphics.lineBetween(lastLandingMark.x, lastLandingMark.y - 14, lastLandingMark.x, lastLandingMark.y + 14);
    addAxisLabel(`X ${lastLandingMark.width} | Y ${lastLandingMark.depth}`, lastLandingMark.x + 16, lastLandingMark.y - 10, '#ffd24a');
}

function drawPlayersDebug() {
    const items = [
        { sprite: playerSetter, label: 'P1 LEV' , color: 0x22ff88 },
        { sprite: playerLeft, label: 'P2 ATA E' , color: 0x22ff88 },
        { sprite: playerRight, label: 'P3 ATA D' , color: 0x22ff88 },
        { sprite: playerBacker, label: 'P4 REC' , color: 0x22ff88 },
        { sprite: opponentFront, label: 'A1 LEV' , color: 0xff8a8a },
        { sprite: oppLeft, label: 'A2 ATA E' , color: 0xff8a8a },
        { sprite: oppRight, label: 'A3 ATA D' , color: 0xff8a8a },
        { sprite: opponentBack, label: 'A4 REC' , color: 0xff8a8a }
    ];

    items.forEach(({ sprite, label, color }) => {
        if (!sprite) return;
        const isCtrl = sprite === getControlledPlayer();

        const cx = sprite.x;
        const cy = sprite.y;
        devGraphics.lineStyle(1, isCtrl ? 0xffffff : color, isCtrl ? 0.9 : 0.65);
        devGraphics.lineBetween(cx - 18, cy, cx + 18, cy);
        devGraphics.lineBetween(cx, cy - 18, cx, cy + 18);

        // Raio de recepção (círculo no chão)
        if (sprite === getControlledPlayer()) {
            devGraphics.lineStyle(2, 0x22ff88, 0.75);
            devGraphics.strokeCircle(cx, cy, RECEIVE.radius);
        }

        const t = devGraphics.scene.add.text(cx - 34, cy - 86, isCtrl ? `${label} (CTRL)` : label, {
            fontFamily: 'Consolas, monospace',
            fontSize: '11px',
            color: isCtrl ? '#ffffff' : `#${color.toString(16).padStart(6, '0')}`,
            backgroundColor: 'rgba(0,0,0,0.35)',
            padding: { x: 4, y: 2 }
        });
        t.setDepth(18);
        devPositionLabels.push(t);
    });

    // Marca onde a bola "tocou" para a última manchete
    if (lastContactMark && devGraphics && devGraphics.scene) {
        const age = devGraphics.scene.time.now - lastContactMark.t;
        if (age < 1200) {
            const a = Phaser.Math.Clamp(1 - age / 1200, 0, 1);
            devGraphics.lineStyle(2, 0xffd24a, 0.9 * a);
            devGraphics.strokeCircle(lastContactMark.x, lastContactMark.y, 10);
            devGraphics.lineBetween(lastContactMark.x - 12, lastContactMark.y, lastContactMark.x + 12, lastContactMark.y);
            devGraphics.lineBetween(lastContactMark.x, lastContactMark.y - 12, lastContactMark.x, lastContactMark.y + 12);
        }
    }
}

function drawBallAnchorDebug() {
    if (!player || !ballBody) return;
    const headGroundY = getHeadBallGroundY();
    // Feet position (physics ground)
    devGraphics.fillStyle(0xffffff, 0.75);
    devGraphics.fillCircle(player.x, player.y, 3);
    addAxisLabel('PE', player.x + 6, player.y - 7, '#ffffff');

    // "Head anchor" ground Y where the ball body is placed during READY
    devGraphics.fillStyle(0x00e5ff, 0.75);
    devGraphics.fillCircle(player.x, headGroundY, 3);
    addAxisLabel('ANCORA BOLA', player.x + 6, headGroundY - 7, '#00e5ff');

    // Ball body ground position (can drift during physics)
    devGraphics.fillStyle(0xfff266, 0.85);
    devGraphics.fillCircle(ballBody.x, ballBody.y, 3);
    addAxisLabel('BOLA (CHAO)', ballBody.x + 6, ballBody.y - 7, '#fff266');
}

function drawCourtAxis() {
    devGraphics.lineStyle(1, 0x00e5ff, 0.35);

    for (let x = 100; x <= 700; x += 100) {
        devGraphics.lineBetween(x, 70, x, 548);
        addAxisLabel(`${x - COURT.centerX}`, x + 2, 552, '#00e5ff');
    }

    devGraphics.lineStyle(1, 0xfff266, 0.35);
    for (let depth = DEPTH.opponentBack; depth <= DEPTH.playerBack; depth += 50) {
        const y = NET.y + depth;
        if (y < 0 || y > COURT.height) continue;
        devGraphics.lineBetween(92, y, 708, y);
        addAxisLabel(`${depth}`, 712, y - 7, '#fff266');
    }

    devGraphics.lineStyle(3, 0x00e5ff, 0.7);
    devGraphics.lineBetween(COURT.centerX, 70, COURT.centerX, 548);
    devGraphics.lineStyle(3, 0xfff266, 0.7);
    devGraphics.lineBetween(92, NET.y, 708, NET.y);
}

function drawHeightRuler() {
    const baseX = player.x + 58;
    // Usa a posição Y do physics (que é o chão)
    const baseY = player.y - PLAYER.headOffsetY; 
    const maxZ = BALL.tossTargetZ;
    const topY = baseY - maxZ * BALL.zToPixels;

    devGraphics.lineStyle(2, 0xff3355, 0.9);
    devGraphics.lineBetween(baseX, baseY, baseX, topY);

    for (let z = 0; z <= maxZ; z += 25) {
        const y = baseY - z * BALL.zToPixels;
        devGraphics.lineBetween(baseX - 6, y, baseX + 6, y);
        addAxisLabel(`${z}`, baseX + 10, y - 7, '#ffccd4');
    }
}

function drawNetMarker() {
    const topY = NET.y - (NET.heightZ * BALL.zToPixels + NET.markerExtraPx);

    devGraphics.lineStyle(3, 0xff3355, 0.75);
    devGraphics.lineBetween(92, NET.y, 708, NET.y);
    addAxisLabel(`REDE Y ${NET.y}`, 96, NET.y - 18, '#ffccd4');

    devGraphics.lineStyle(2, 0xff3355, 0.95);
    devGraphics.lineBetween(NET.markerX, NET.y, NET.markerX, topY);
    devGraphics.lineBetween(NET.markerX - 8, NET.y, NET.markerX + 8, NET.y);
    devGraphics.lineBetween(NET.markerX - 8, topY, NET.markerX + 8, topY);
    // Visual scale marker: top of net corresponds to -100 on this ruler (per your calibration).
    addAxisLabel(`-100`, NET.markerX + 12, topY - 7, '#ffccd4');
    const midY = (NET.y + topY) / 2;
    devGraphics.lineBetween(NET.markerX - 6, midY, NET.markerX + 6, midY);
    addAxisLabel(`-50`, NET.markerX + 12, midY - 7, '#ffccd4');
}

function performRallyHit(scene) {
    const attacker = getControlledPlayer();
    state = 'FLYING';
    lastTouch = 'PLAYER_HIT';
    opponentReceiveAttemptedForPlayerHit = false;
    opponentDefenseStartedAtMs = 0;
    opponentLowServeDecision = null;
    resetOpponentLetOutDecision();
    const attackPower = Phaser.Math.Clamp((ballZ - 45) / 85, 0, 1);
    lastPlayerAttackPower = attackPower;
    lastPlayerShotKind = 'ATTACK';
    const targetDepth = Phaser.Math.Linear(230, 250, attackPower);
    const targetY = NET.y - targetDepth;
    const flightTime = Phaser.Math.Linear(0.94, 0.72, attackPower);
    ballVZ = Phaser.Math.Linear(-210, -320, attackPower);
    beginShotLog('ATAQUE', 'JOGADOR', {
        force: attackPower.toFixed(2),
        quality: controlledRole
    });

    // Atacante da esquerda corta para dentro (direita); atacante da direita corta para dentro (esquerda).
    const attackRole = controlledRole === 'LEFT' || controlledRole === 'RIGHT'
        ? controlledRole
        : attacker.x < COURT.centerX ? 'LEFT' : 'RIGHT';
    const targetInsideX = attackRole === 'LEFT' ? COURT.centerX + 95 : COURT.centerX - 95;
    const desiredVx = (targetInsideX - ballBody.x) / flightTime;
    const desiredVy = (targetY - ballBody.y) / flightTime;
    const maxAttackVX = Phaser.Math.Linear(380, 560, attackPower);
    const appliedVx = Phaser.Math.Clamp(desiredVx, -maxAttackVX, maxAttackVX);
    const appliedVy = Phaser.Math.Clamp(desiredVy, -620, -330);
    const realLandingX = ballBody.x + appliedVx * flightTime;
    const realLandingY = ballBody.y + appliedVy * flightTime;

    ballBody.setVelocity(appliedVx, appliedVy);
    ensureShotNetFromCurrentTrajectory('calculo inicial');
    setShotCalculations({
        'modelo': 'corte jogador',
        'lado ataque': attackRole,
        'forca pela altura': attackPower.toFixed(2),
        'altura do contato': Math.round(ballZ),
        'origem screen': `x ${Math.round(ballBody.x)} y ${Math.round(ballBody.y)} z ${Math.round(ballZ)}`,
        'origem quadra': `x ${screenXToWidth(ballBody.x)} y ${screenYToDepth(ballBody.y)} z ${Math.round(ballZ)}`,
        'alvo solicitado screen': `x ${Math.round(targetInsideX)} y ${Math.round(targetY)}`,
        'alvo solicitado quadra': `x ${screenXToWidth(targetInsideX)} y ${screenYToDepth(targetY)}`,
        'alvo real estimado screen': `x ${Math.round(realLandingX)} y ${Math.round(realLandingY)}`,
        'alvo real estimado quadra': `x ${screenXToWidth(realLandingX)} y ${screenYToDepth(realLandingY)}`,
        'profundidade alvo': `${Math.round(targetDepth)} = Y quadra ${screenYToDepth(targetY)}`,
        'tempo voo': `${flightTime.toFixed(3)}s`,
        'vx necessario': Math.round(desiredVx),
        'vy necessario': Math.round(desiredVy),
        'limite vx ataque': Math.round(maxAttackVX),
        'vx cortado pelo limite': Math.abs(desiredVx) > maxAttackVX ? 'SIM' : 'NAO',
        'vz corte': ballVZ,
        'velocidade aplicada': `vx ${Math.round(ballBody.body.velocity.x)} vy ${Math.round(ballBody.body.velocity.y)} vz ${Math.round(ballVZ)}`,
        'velocidade total aplicada': Math.round(Math.hypot(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ))
    });
    setShotVelocity(ballBody.body.velocity.x, ballBody.body.velocity.y, ballVZ);
    
    playerHitPoseUntil = scene.time.now + 400;
    attacker.setTexture('playerHit');
    scene.time.delayedCall(520, () => {
        if (attacker.texture && attacker.texture.key === 'playerHit') {
            attacker.setTexture('playerBack');
        }
        playerHitPoseUntil = 0;
        if (state === 'FLYING' && lastTouch === 'PLAYER_HIT') {
            setControlledRole('RECEPTOR');
        }
    });
    
    scene.cameras.main.shake(200, 0.015);
    addHitEffect(scene, { shake: 0.01 });
}

function addAxisLabel(text, x, y, color) {
    const label = devGraphics.scene.add.text(x, y, text, {
        fontFamily: 'Consolas, monospace',
        fontSize: '11px',
        color
    });
    label.setDepth(4);
    label.setName('dev-axis-label');
    devLabels.push(label);
}
