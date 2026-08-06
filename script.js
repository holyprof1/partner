(function () {
  "use strict";

  /* ---------------- shared state ---------------- */
  var state = {
    plan: "Elite",
    price: 149,
    credits: 2000,
    creditsUsed: 660,
    promoLimit: 8,
    promoUsed: 5
  };

  /* ---------------- screen navigation ---------------- */
  var screens = document.querySelectorAll(".screen");
  function showScreen(id) {
    screens.forEach(function (s) {
      s.classList.toggle("is-active", s.id === id);
    });
    window.scrollTo({ top: 0, behavior: "auto" });
  }
  document.querySelectorAll("[data-nav]").forEach(function (el) {
    el.addEventListener("click", function (e) {
      e.preventDefault();
      showScreen(el.getAttribute("data-nav"));
    });
  });

  /* ---------------- toast ---------------- */
  var toastEl = document.getElementById("toast");
  var toastMsgEl = document.getElementById("toastMsg");
  var toastTimer;
  function toast(msg) {
    toastMsgEl.textContent = msg;
    toastEl.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toastEl.classList.remove("show");
    }, 2400);
  }
  document.querySelectorAll("[data-toast]").forEach(function (el) {
    el.addEventListener("click", function () {
      toast(el.getAttribute("data-toast"));
    });
  });

  /* ---------------- user menu (Find Industry header) ---------------- */
  var userChip = document.getElementById("userChipFind");
  var userMenu = document.getElementById("userMenuFind");
  if (userChip) {
    userChip.addEventListener("click", function (e) {
      e.stopPropagation();
      userMenu.classList.toggle("is-open");
    });
    document.addEventListener("click", function () {
      userMenu.classList.remove("is-open");
    });
  }

  /* ---------------- button loading helper ---------------- */
  function withLoading(btn, ms, cb) {
    btn.classList.add("is-loading");
    btn.disabled = true;
    setTimeout(function () {
      btn.classList.remove("is-loading");
      btn.disabled = false;
      cb();
    }, ms);
  }

  /* ---------------- 1. login ---------------- */
  var loginForm = document.getElementById("loginForm");
  loginForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var btn = document.getElementById("loginBtn");
    withLoading(btn, 700, function () {
      showScreen("screen-find");
    });
  });

  /* ---------------- 2. find industry — live search ---------------- */
  var searchInput = document.getElementById("industrySearch");
  var cards = document.querySelectorAll("#industryGrid .industry-card");
  var emptyState = document.getElementById("emptyState");
  searchInput.addEventListener("input", function () {
    var q = searchInput.value.trim().toLowerCase();
    var visible = 0;
    cards.forEach(function (c) {
      var match = c.getAttribute("data-name").indexOf(q) !== -1;
      c.style.display = match ? "" : "none";
      if (match) visible++;
    });
    emptyState.style.display = visible === 0 ? "block" : "none";
  });

  /* ---------------- 3. restaurant industry — plan selection ---------------- */
  var sumPlan = document.getElementById("sumPlan");
  var sumCredits = document.getElementById("sumCredits");
  var sumTotal = document.getElementById("sumTotal");

  document.querySelectorAll("[data-plan]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      state.plan = btn.getAttribute("data-plan");
      state.price = parseInt(btn.getAttribute("data-price"), 10);
      state.credits = parseInt(btn.getAttribute("data-credits"), 10);
      state.promoLimit = parseInt(btn.getAttribute("data-promos"), 10);

      sumPlan.textContent = state.plan + " — Restaurant";
      sumCredits.textContent = state.credits.toLocaleString() + " / mo";
      sumTotal.textContent = "$" + state.price.toFixed(2);

      showScreen("screen-payment");
    });
  });

  /* ---------------- 4. payment — live card mockup + formatting ---------------- */
  var ccName = document.getElementById("ccName");
  var ccNumber = document.getElementById("ccNumber");
  var ccExp = document.getElementById("ccExp");
  var ccNameDisplay = document.getElementById("ccNameDisplay");
  var ccNumberDisplay = document.getElementById("ccNumberDisplay");
  var ccExpDisplay = document.getElementById("ccExpDisplay");

  ccName.addEventListener("input", function () {
    ccNameDisplay.textContent = ccName.value.trim().toUpperCase() || "YOUR NAME";
  });

  ccNumber.addEventListener("input", function () {
    var digits = ccNumber.value.replace(/\D/g, "").slice(0, 16);
    var groups = digits.match(/.{1,4}/g) || [];
    ccNumber.value = groups.join(" ");
    var display = [];
    for (var i = 0; i < 4; i++) {
      display.push(groups[i] ? groups[i].padEnd(4, "•") : "••••");
    }
    ccNumberDisplay.textContent = display.join(" ");
  });

  ccExp.addEventListener("input", function () {
    var digits = ccExp.value.replace(/\D/g, "").slice(0, 4);
    if (digits.length > 2) digits = digits.slice(0, 2) + "/" + digits.slice(2);
    ccExp.value = digits;
    ccExpDisplay.textContent = digits || "MM/YY";
  });

  var successOverlay = document.getElementById("successOverlay");
  var successTitle = document.getElementById("successTitle");
  var successSub = document.getElementById("successSub");
  function showSuccess(title, sub, then) {
    successTitle.textContent = title;
    successSub.textContent = sub;
    successOverlay.classList.add("show");
    setTimeout(function () {
      successOverlay.classList.remove("show");
      then();
    }, 1300);
  }

  var statPlan = document.getElementById("statPlan");
  var walletEyebrow = document.getElementById("walletEyebrow");
  var statCreditsUsed = document.getElementById("statCreditsUsed");
  var statCreditsTotal = document.getElementById("statCreditsTotal");
  var statCreditsBar = document.getElementById("statCreditsBar");
  var statPromoUsed = document.getElementById("statPromoUsed");
  var statPromoTotal = document.getElementById("statPromoTotal");
  var statPromoBar = document.getElementById("statPromoBar");

  function refreshWallet() {
    statPlan.textContent = state.plan;
    walletEyebrow.textContent = state.plan + " · Restaurant";
    var remaining = Math.max(state.credits - state.creditsUsed, 0);
    statCreditsUsed.textContent = remaining.toLocaleString();
    statCreditsTotal.textContent = state.credits.toLocaleString();
    var creditPct = Math.min(100, Math.round((remaining / state.credits) * 100));
    statCreditsBar.style.width = creditPct + "%";

    var promoLimitLabel = state.promoLimit >= 999 ? "Unlimited" : state.promoLimit;
    statPromoUsed.textContent = state.promoUsed;
    statPromoTotal.textContent = promoLimitLabel;
    var promoPct = state.promoLimit >= 999 ? 18 : Math.min(100, Math.round((state.promoUsed / state.promoLimit) * 100));
    statPromoBar.style.width = promoPct + "%";
  }

  document.getElementById("paymentForm").addEventListener("submit", function (e) {
    e.preventDefault();
    var btn = document.getElementById("payBtn");
    withLoading(btn, 900, function () {
      showSuccess("Payment confirmed", state.plan + " is now active. Redirecting to your SWESS Wallet…", function () {
        refreshWallet();
        showScreen("screen-wallet");
      });
    });
  });

  /* ---------------- 6. create promotion — live preview + upload ---------------- */
  var promoTitle = document.getElementById("promoTitle");
  var promoDesc = document.getElementById("promoDesc");
  var promoDestination = document.getElementById("promoDestination");
  var promoDate = document.getElementById("promoDate");
  var ppTitle = document.getElementById("ppTitle");
  var ppDesc = document.getElementById("ppDesc");
  var ppDestination = document.getElementById("ppDestination");
  var ppDate = document.getElementById("ppDate");
  var ppMedia = document.getElementById("ppMedia");

  promoTitle.addEventListener("input", function () {
    ppTitle.textContent = promoTitle.value.trim() || "Your promotion title";
  });
  promoDesc.addEventListener("input", function () {
    ppDesc.textContent = promoDesc.value.trim() || "Your description will appear here as you type.";
  });
  promoDestination.addEventListener("change", function () {
    ppDestination.textContent = promoDestination.value;
  });
  promoDate.addEventListener("input", function () {
    if (!promoDate.value) { ppDate.textContent = "No date set"; return; }
    var d = new Date(promoDate.value + "T00:00:00");
    ppDate.textContent = d.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
  });

  var uploadBox = document.getElementById("uploadBox");
  var uploadInput = document.getElementById("uploadInput");
  var uploadPreview = document.getElementById("uploadPreview");
  var uploadPreviewImg = document.getElementById("uploadPreviewImg");

  uploadBox.addEventListener("click", function () { uploadInput.click(); });
  uploadInput.addEventListener("change", function () {
    var file = uploadInput.files && uploadInput.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      uploadPreviewImg.src = e.target.result;
      uploadPreview.style.display = "block";
      ppMedia.innerHTML = '<img src="' + e.target.result + '" alt="">';
    };
    reader.readAsDataURL(file);
  });

  var promoPanel = document.getElementById("promoPanel");
  document.getElementById("promoForm").addEventListener("submit", function (e) {
    e.preventDefault();
    var btn = document.getElementById("publishBtn");
    var title = promoTitle.value.trim() || "Untitled Promotion";

    withLoading(btn, 900, function () {
      showSuccess("Promotion published", "Syncing “" + title + "” to Hulahoot…", function () {
        var row = document.createElement("div");
        row.className = "promo-row";
        row.innerHTML =
          '<div><div class="name">' + escapeHtml(title) + '</div><div class="sub">' + (ppDate.textContent === "No date set" ? "Publishing now" : "Starts " + ppDate.textContent) + '</div></div>' +
          '<div class="badge badge-success">Active</div>' +
          '<div class="num mono">0 SWESS</div>' +
          '<div class="sub">Created just now</div>';
        promoPanel.insertBefore(row, promoPanel.children[1]);

        state.promoUsed += 1;
        refreshWallet();

        promoTitle.value = "";
        promoDesc.value = "";
        promoDate.value = "";
        ppTitle.textContent = "Your promotion title";
        ppDesc.textContent = "Your description will appear here as you type.";
        ppDate.textContent = "No date set";
        ppMedia.innerHTML = '<svg><use href="#i-image"></use></svg>';
        uploadPreview.style.display = "none";

        showScreen("screen-wallet");
      });
    });
  });

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  /* ---------------- init ---------------- */
  refreshWallet();
})();
