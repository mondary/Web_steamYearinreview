const reveals = document.querySelectorAll(".reveal");

reveals.forEach((el) => {
  const delay = el.dataset.delay;
  if (delay) {
    el.style.setProperty("--delay", delay);
  }
});

const stats = {
  account_age: document.querySelector("#stat-account-age"),
  games: document.querySelector("#stat-games"),
};

const profileMeta = {
  status: document.querySelector("#profile-status"),
  level: document.querySelector("#profile-level-value"),
  games: document.querySelector("#profile-games-value"),
  member: document.querySelector("#profile-member-value"),
};

const setStatText = (node, value) => {
  if (!node) return;
  node.textContent = value || "--";
};

const yirConfigs = [
  {
    endpoint: "backend/yir_2025.php",
    ids: {
      gamesPlayed: "#yir-games-played",
      gamesDelta: "#yir-games-delta",
      newGames: "#yir-new-games",
      demos: "#yir-demos",
    },
  },
  {
    endpoint: "backend/yir_2024.php",
    ids: {
      gamesPlayed: "#yir-2024-games-played",
      gamesDelta: "#yir-2024-games-delta",
      newGames: "#yir-2024-new-games",
      demos: "#yir-2024-demos",
    },
  },
  {
    endpoint: "backend/yir_2023.php",
    ids: {
      gamesPlayed: "#yir-2023-games-played",
      gamesDelta: "#yir-2023-games-delta",
      newGames: "#yir-2023-new-games",
      demos: "#yir-2023-demos",
    },
  },
  {
    endpoint: "backend/yir_2022.php",
    ids: {
      gamesPlayed: "#yir-2022-games-played",
      gamesDelta: "#yir-2022-games-delta",
      newGames: "#yir-2022-new-games",
      demos: "#yir-2022-demos",
    },
  },
];

yirConfigs.forEach(({ endpoint, ids }) => {
  const nodes = {
    gamesPlayed: document.querySelector(ids.gamesPlayed),
    gamesDelta: document.querySelector(ids.gamesDelta),
    newGames: document.querySelector(ids.newGames),
    demos: document.querySelector(ids.demos),
  };

  fetch(endpoint)
    .then((response) => response.json())
    .then((data) => {
      if (!data || data.ok !== true) {
        return;
      }

      if (typeof data.games_played === "number") {
        setStatText(nodes.gamesPlayed, data.games_played);
      }

      if (typeof data.new_games === "number") {
        setStatText(nodes.newGames, data.new_games);
      }

      if (typeof data.demos_played === "number") {
        setStatText(nodes.demos, data.demos_played);
      }

      if (typeof data.games_delta === "number") {
        if (data.games_delta < 0) {
          setStatText(
            nodes.gamesDelta,
            `${Math.abs(data.games_delta)} jeux de moins que l'annee derniere`
          );
        } else if (data.games_delta > 0) {
          setStatText(
            nodes.gamesDelta,
            `${data.games_delta} jeux de plus que l'annee derniere`
          );
        } else {
          setStatText(nodes.gamesDelta, "autant que l'annee derniere");
        }
      }
    })
    .catch(() => {});
});

const monthNames = [
  "Janvier",
  "Fevrier",
  "Mars",
  "Avril",
  "Mai",
  "Juin",
  "Juillet",
  "Aout",
  "Septembre",
  "Octobre",
  "Novembre",
  "Decembre",
];

const timelineTarget = document.querySelector("#timeline-2025");
const buildTimelineItem = (entry) => {
  const date = new Date(entry.rtime_month * 1000);
  const monthLabel = monthNames[date.getUTCMonth()] || "Mois";

  const item = document.createElement("div");
  item.className = "timeline-item";

  const marker = document.createElement("div");
  marker.className = "timeline-marker";

  const content = document.createElement("div");
  content.className = "timeline-content";

  const month = document.createElement("div");
  month.className = "timeline-month";
  month.textContent = `${monthLabel} 2025`;

  const games = document.createElement("div");
  games.className = "timeline-games";

  (entry.games || []).forEach(({ appid, percent }) => {
    const link = document.createElement("a");
    link.className = "timeline-game";
    link.href = `https://store.steampowered.com/app/${appid}`;
    link.target = "_blank";
    link.rel = "noreferrer";

    const img = document.createElement("img");
    img.alt = `App ${appid}`;
    img.loading = "lazy";
    img.src = `https://shared.fastly.steamstatic.com/store_item_assets/steam/apps/${appid}/library_600x900.jpg`;
    link.appendChild(img);

    if (typeof percent === "number" && !Number.isNaN(percent)) {
      const badge = document.createElement("span");
      badge.className = "timeline-percent";
      badge.textContent = `${percent}%`;
      link.appendChild(badge);
    }

    games.appendChild(link);
  });

  content.appendChild(month);
  content.appendChild(games);
  item.appendChild(marker);
  item.appendChild(content);

  return item;
};

fetch("backend/yir_2025.php")
  .then((response) => response.json())
  .then((data) => {
    if (!timelineTarget || !data || data.ok !== true || !Array.isArray(data.timeline)) {
      return;
    }

    const sorted = [...data.timeline].sort((a, b) => b.rtime_month - a.rtime_month);
    sorted.forEach((entry) => {
      timelineTarget.appendChild(buildTimelineItem(entry));
    });
  })
  .catch(() => {});

fetch("backend/steam_profile.php")
  .then((response) => response.json())
  .then((data) => {
    if (!data || data.ok !== true) {
      return;
    }

    if (data.status) {
      setStatText(profileMeta.status, data.status);
    }

    if (data.level) {
      setStatText(profileMeta.level, data.level);
    }

    if (data.games_owned) {
      setStatText(profileMeta.games, data.games_owned);
    }

    if (data.member_since) {
      setStatText(profileMeta.member, data.member_since);
    }

    if (data.account_age && (!stats.account_age || stats.account_age.textContent === "--")) {
      setStatText(stats.account_age, data.account_age);
    }

    if (data.games_played && data.games_owned && (!stats.games || stats.games.textContent === "--")) {
      setStatText(stats.games, `${data.games_played} / ${data.games_owned}`);
    }
  })
  .catch(() => {
    setStatText(profileMeta.status, "Statut indisponible");
  });
