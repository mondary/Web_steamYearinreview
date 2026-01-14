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

const steamidSelect = document.querySelector("#steamid-select");
const steamidInput = document.querySelector("#steamid-input");
const steamidAdd = document.querySelector("#steamid-add");
const steamidLinks = document.querySelectorAll("[data-steamid-link]");
const defaultSteamId = "76561197974617624";

const profileMeta = {
  level: document.querySelector("#profile-level-value"),
  games: document.querySelector("#profile-games-value"),
  member: document.querySelector("#profile-member-value"),
  name: document.querySelector("#profile-name"),
  steamid: document.querySelector("#profile-steamid-value"),
};

const setStatText = (node, value) => {
  if (!node) return;
  node.textContent = value || "--";
};

const isSteamId = (value) => /^\d{17}$/.test(value);

const loadSteamIdState = () => {
  const listRaw = localStorage.getItem("steamidList");
  const activeRaw = localStorage.getItem("steamidActive");
  const list = listRaw ? JSON.parse(listRaw) : [defaultSteamId];
  const active = isSteamId(activeRaw) ? activeRaw : list[0] || defaultSteamId;
  return { list: Array.from(new Set(list.filter(isSteamId))), active };
};

const saveSteamIdState = (list, active) => {
  localStorage.setItem("steamidList", JSON.stringify(list));
  localStorage.setItem("steamidActive", active);
};

const renderSteamIdSelect = (list, active) => {
  if (!steamidSelect) return;
  steamidSelect.innerHTML = "";
  list.forEach((id) => {
    const option = document.createElement("option");
    option.value = id;
    option.textContent = id;
    if (id === active) option.selected = true;
    steamidSelect.appendChild(option);
  });
};

const updateSteamIdLinks = (steamid) => {
  steamidLinks.forEach((link) => {
    const template = link.getAttribute("data-steamid-link");
    if (template) {
      link.href = template.replace("{steamid}", steamid);
    }
  });
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

const buildTimelineItem = (entry, year) => {
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
  month.textContent = `${monthLabel} ${year}`;

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

const renderTimeline = (targetId, endpoint, year, steamid) => {
  const timelineTarget = document.querySelector(targetId);
  if (!timelineTarget) return;
  timelineTarget.innerHTML = "";

  fetch(`${endpoint}?steamid=${steamid}`)
    .then((response) => response.json())
    .then((data) => {
      if (!data || data.ok !== true || !Array.isArray(data.timeline)) {
        return;
      }

      const sorted = [...data.timeline].sort((a, b) => b.rtime_month - a.rtime_month);
      sorted.forEach((entry) => {
        timelineTarget.appendChild(buildTimelineItem(entry, year));
      });
    })
    .catch(() => {});
};

const renderYearStats = (steamid) => {
  yirConfigs.forEach(({ endpoint, ids }) => {
    const nodes = {
      gamesPlayed: document.querySelector(ids.gamesPlayed),
      gamesDelta: document.querySelector(ids.gamesDelta),
      newGames: document.querySelector(ids.newGames),
      demos: document.querySelector(ids.demos),
    };

    fetch(`${endpoint}?steamid=${steamid}`)
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
};

const renderProfile = (steamid) => {
  fetch(`backend/steam_profile.php?steamid=${steamid}`)
    .then((response) => response.json())
    .then((data) => {
      if (!data || data.ok !== true) {
        return;
      }

      if (data.persona_name && profileMeta.name) {
        setStatText(profileMeta.name, data.persona_name);
      }

      if (data.steamid && profileMeta.steamid) {
        setStatText(profileMeta.steamid, data.steamid);
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
    .catch(() => {});
};

const applySteamId = (steamid) => {
  if (!isSteamId(steamid)) return;
  setStatText(profileMeta.steamid, steamid);
  updateSteamIdLinks(steamid);
  renderYearStats(steamid);
  renderTimeline("#timeline-2025", "backend/yir_2025.php", 2025, steamid);
  renderTimeline("#timeline-2024", "backend/yir_2024.php", 2024, steamid);
  renderTimeline("#timeline-2023", "backend/yir_2023.php", 2023, steamid);
  renderTimeline("#timeline-2022", "backend/yir_2022.php", 2022, steamid);
  renderProfile(steamid);
};

if (steamidAdd && steamidInput && steamidSelect) {
  steamidAdd.addEventListener("click", () => {
    const value = steamidInput.value.trim();
    if (!isSteamId(value)) return;
    const state = loadSteamIdState();
    const list = Array.from(new Set([...state.list, value]));
    renderSteamIdSelect(list, value);
    saveSteamIdState(list, value);
    steamidInput.value = "";
    applySteamId(value);
  });

  steamidSelect.addEventListener("change", () => {
    const value = steamidSelect.value;
    const state = loadSteamIdState();
    saveSteamIdState(state.list, value);
    applySteamId(value);
  });
}

const state = loadSteamIdState();
renderSteamIdSelect(state.list, state.active);
applySteamId(state.active);
