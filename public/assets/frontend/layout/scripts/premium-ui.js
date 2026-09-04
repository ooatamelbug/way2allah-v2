/* ==========================================================================
   WAY2ALLAH.COM — PREMIUM UI/UX JS UTILITIES
   Architecture: Lightweight companion script for touch interactions, accessibility, & image fallbacks
   ========================================================================== */

(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    // 1. Channel Logo Touch Tooltip Handler (Fix for Touch Devices)
    var isTouch =
      "ontouchstart" in window ||
      navigator.maxTouchPoints > 0 ||
      navigator.msMaxTouchPoints > 0;

    if (isTouch) {
      var channelLogos = document.querySelectorAll("a.channel-logo");

      channelLogos.forEach(function (logo) {
        logo.addEventListener("click", function (e) {
          // If first tap on this channel logo, prevent immediate navigation and show tooltip
          if (!logo.classList.contains("w2a-touch-active")) {
            e.preventDefault();

            // Close any active tooltips on other channel logos
            channelLogos.forEach(function (otherLogo) {
              if (otherLogo !== logo) {
                otherLogo.classList.remove("w2a-touch-active");
              }
            });

            logo.classList.add("w2a-touch-active");
          }
          // Second tap on the active logo proceeds with link navigation (href)
        });
      });

      // Dismiss active tooltips when tapping outside channel logo cards
      document.addEventListener("click", function (e) {
        if (!e.target.closest("a.channel-logo")) {
          document
            .querySelectorAll("a.channel-logo.w2a-touch-active")
            .forEach(function (activeLogo) {
              activeLogo.classList.remove("w2a-touch-active");
            });
        }
      });
    }

    // 2. Broken Image Fallback Handler for Card & List Rows
    // SVG Data URI placeholder circle with a subtle document/media icon
    var fallbackSvg =
      'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 38 38" fill="none"><circle cx="19" cy="19" r="19" fill="%23EAF3FA"/><path d="M19 12C15.13 12 12 15.13 12 19C12 22.87 15.13 26 19 26C22.87 26 26 22.87 26 19C26 15.13 22.87 12 19 12ZM17 22.5V15.5L22 19L17 22.5Z" fill="%23125688"/></svg>';

    var cardImages = document.querySelectorAll(
      "ul.vars img, ul.homecss img, .portlet.box img, div.author img, .telawah-author img",
    );
    cardImages.forEach(function (img) {
      img.addEventListener("error", function () {
        if (this.getAttribute("data-w2a-fallback") !== "true") {
          this.setAttribute("data-w2a-fallback", "true");
          this.src = fallbackSvg;
          this.classList.add("w2a-img-fallback");
        }
      });
    });

    // 3. Media Rail Navigation & Mouse Drag-to-Scroll Handler (.w2a-now-watching-rail)
    var rails = document.querySelectorAll(".w2a-now-watching-rail");

    rails.forEach(function (rail) {
      var isDown = false;
      var hasMoved = false;
      var startX;
      var scrollLeft;

      // Prevent native HTML5 image & link dragging ghost previews
      rail.querySelectorAll("img, a").forEach(function (el) {
        el.setAttribute("draggable", "false");
        el.addEventListener("dragstart", function (e) {
          e.preventDefault();
        });
      });

      rail.addEventListener("mousedown", function (e) {
        isDown = true;
        hasMoved = false;
        rail.classList.add("w2a-dragging");
        startX = e.pageX - rail.offsetLeft;
        scrollLeft = rail.scrollLeft;
      });

      rail.addEventListener("mouseleave", function () {
        isDown = false;
        rail.classList.remove("w2a-dragging");
      });

      rail.addEventListener("mouseup", function () {
        isDown = false;
        rail.classList.remove("w2a-dragging");
      });

      rail.addEventListener("mousemove", function (e) {
        if (!isDown) return;
        var x = e.pageX - rail.offsetLeft;
        var walk = (x - startX) * 2;
        if (Math.abs(x - startX) > 5) {
          hasMoved = true;
        }
        if (hasMoved) {
          e.preventDefault();
          rail.scrollLeft = scrollLeft - walk;
        }
      });

      // Prevent accidental link navigation on mouseup after dragging
      rail.addEventListener(
        "click",
        function (e) {
          if (hasMoved) {
            e.preventDefault();
            e.stopPropagation();
            hasMoved = false;
          }
        },
        true,
      );
    });

    // Arrow Button Navigation Click Delegation
    document.addEventListener("click", function (e) {
      var prevBtn = e.target.closest(".w2a-rail-prev");
      var nextBtn = e.target.closest(".w2a-rail-next");

      if (prevBtn) {
        var container = prevBtn.closest(".w2a-now-watching");
        if (container) {
          var rail = container.querySelector(".w2a-now-watching-rail");
          if (rail) {
            rail.scrollBy({
              left: 320,
              behavior: prefersReducedMotion ? "auto" : "smooth",
            });
          }
        }
      }

      if (nextBtn) {
        var container = nextBtn.closest(".w2a-now-watching");
        if (container) {
          var rail = container.querySelector(".w2a-now-watching-rail");
          if (rail) {
            rail.scrollBy({
              left: -320,
              behavior: prefersReducedMotion ? "auto" : "smooth",
            });
          }
        }
      }
    });

    // 4. Hero Banner Slider Controller (.w2a-hero-slider-wrap)
    var heroSliders = document.querySelectorAll(".w2a-hero-slider-wrap");

    heroSliders.forEach(function (slider) {
      var slides = slider.querySelectorAll(".w2a-hero-slide");
      var dots = slider.querySelectorAll(".w2a-hero-dot");
      var prevBtn = slider.querySelector(".w2a-hero-prev");
      var nextBtn = slider.querySelector(".w2a-hero-next");

      if (slides.length <= 1) return;

      var currentIndex = 0;
      var timer = null;

      function goToSlide(index) {
        slides.forEach(function (slide, i) {
          if (i === index) {
            slide.classList.add("active");
            slide.setAttribute("aria-hidden", "false");
          } else {
            slide.classList.remove("active");
            slide.setAttribute("aria-hidden", "true");
          }
        });

        dots.forEach(function (dot, i) {
          if (i === index) {
            dot.classList.add("active");
            dot.setAttribute("aria-current", "true");
          } else {
            dot.classList.remove("active");
            dot.setAttribute("aria-current", "false");
          }
        });

        currentIndex = index;
      }

      function nextSlide() {
        var next = (currentIndex + 1) % slides.length;
        goToSlide(next);
      }

      function prevSlide() {
        var prev = (currentIndex - 1 + slides.length) % slides.length;
        goToSlide(prev);
      }

      function startTimer() {
        stopTimer();
        if (prefersReducedMotion || document.hidden) return;
        timer = setInterval(nextSlide, 5000);
      }

      function stopTimer() {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
      }

      if (prevBtn) {
        prevBtn.addEventListener("click", function (e) {
          e.preventDefault();
          prevSlide();
          startTimer();
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener("click", function (e) {
          e.preventDefault();
          nextSlide();
          startTimer();
        });
      }

      dots.forEach(function (dot, i) {
        dot.addEventListener("click", function (e) {
          e.preventDefault();
          goToSlide(i);
          startTimer();
        });
      });

      slider.addEventListener("mouseenter", stopTimer);
      slider.addEventListener("mouseleave", startTimer);
      slider.addEventListener("focusin", stopTimer);
      slider.addEventListener("focusout", startTimer);
      slider.addEventListener("keydown", function (e) {
        if (e.key === "ArrowRight") {
          e.preventDefault();
          prevSlide();
        } else if (e.key === "ArrowLeft") {
          e.preventDefault();
          nextSlide();
        }
      });

      startTimer();
    });

    // 5. Header Menu Item Overflow Controller ("... / المزيد" Dropdown)
    function handleHeaderMenuOverflow() {
      var navUl = document.querySelector(".header-navigation > ul");
      if (!navUl) return;

      var container = document.querySelector(".header > .container");
      var logo = document.querySelector(".header .site-logo");
      var searchLi = navUl.querySelector(".menu-search");
      if (!container || !logo || !searchLi) return;

      // Ensure "More / ..." dropdown container exists
      var moreLi = navUl.querySelector(".w2a-more-menu");
      if (!moreLi) {
        moreLi = document.createElement("li");
        moreLi.className = "dropdown w2a-more-menu";
        moreLi.innerHTML =
          '<a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-ellipsis-h"></i> <i class="fa fa-angle-down"></i></a><ul class="dropdown-menu w2a-more-dropdown"></ul>';
        navUl.insertBefore(moreLi, searchLi);
      }
      var moreUl = moreLi.querySelector(".w2a-more-dropdown");

      // Move items back to top level first to re-measure cleanly
      var hiddenItems = Array.from(moreUl.children);
      hiddenItems.forEach(function (item) {
        navUl.insertBefore(item, moreLi);
      });
      moreLi.style.display = "none";

      // On mobile screens (< 992px), Metronic sidebar drawer handles navigation
      if (window.innerWidth < 992) return;

      // Available width for top nav items
      var containerWidth = container.clientWidth;
      var logoWidth = logo.offsetWidth;
      var searchWidth = searchLi.offsetWidth;
      var availableWidth = containerWidth - logoWidth - searchWidth - 60; // 60px safety padding

      var topItems = Array.from(navUl.children).filter(function (li) {
        return li !== searchLi && li !== moreLi;
      });

      var currentWidth = 0;
      var overflowed = false;

      topItems.forEach(function (li) {
        var liWidth = li.offsetWidth || 100;
        if (!overflowed && currentWidth + liWidth <= availableWidth) {
          currentWidth += liWidth;
        } else {
          overflowed = true;
          moreUl.appendChild(li);
        }
      });

      if (overflowed && moreUl.children.length > 0) {
        moreLi.style.display = "inline-block";
      }
    }

    handleHeaderMenuOverflow();
    window.addEventListener("resize", function () {
      clearTimeout(window.__w2a_nav_timer);
      window.__w2a_nav_timer = setTimeout(handleHeaderMenuOverflow, 150);
    });

    // 6. Glassmorphic Search Modal Dialog Controller
    var searchBox = document.querySelector(".header .search-box");
    var searchTriggers = document.querySelectorAll(
      ".w2a-search-trigger-btn, .header .search-btn, li.menu-search > i",
    );
    var searchReturnFocus = null;

    function openSearchModal() {
      if (!searchBox) return;
      searchReturnFocus = document.activeElement;
      searchBox.classList.add("w2a-modal-open");
      searchBox.setAttribute("aria-hidden", "false");
      if (window.jQuery) {
        window.jQuery(".search-box").stop(true, true).show().css("display", "flex");
        window.jQuery(".search-btn").addClass("show-search-icon");
      }
      document.body.style.overflow = "hidden";
      var firstInput = searchBox.querySelector("#w2a_kh_title");
      if (firstInput) {
        setTimeout(function () {
          firstInput.focus();
        }, 100);
      }
    }

    function closeSearchModal() {
      if (!searchBox) return;
      searchBox.classList.remove("w2a-modal-open");
      searchBox.setAttribute("aria-hidden", "true");
      if (window.jQuery) {
        window.jQuery(".search-box").stop(true, true).hide();
        window.jQuery(".search-btn").removeClass("show-search-icon");
      }
      document.body.style.overflow = "";
      if (searchReturnFocus && typeof searchReturnFocus.focus === "function") {
        searchReturnFocus.focus();
      }
    }

    searchTriggers.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        if (e.target.closest(".search-box")) return;
        e.preventDefault();
        e.stopPropagation();
        openSearchModal();
      });
    });

    if (searchBox) {
      // Direct click handler inside searchBox (captures clicks directly, bypassing legacy e.stopPropagation traps)
      searchBox.addEventListener("click", function (e) {
        var closeBtn = e.target.closest(".w2a-search-close-btn");
        if (closeBtn) {
          e.preventDefault();
          e.stopPropagation();
          closeSearchModal();
          return;
        }

        // If user clicked backdrop overlay outside the modal card, close modal
        if (!e.target.closest(".w2a-search-modal-card")) {
          e.preventDefault();
          e.stopPropagation();
          closeSearchModal();
        }
      });
    }

    document.addEventListener("keydown", function (e) {
      if (
        e.key === "Escape" &&
        searchBox &&
        (searchBox.classList.contains("w2a-modal-open") || (searchBox.offsetWidth > 0 && searchBox.offsetHeight > 0))
      ) {
        closeSearchModal();
      }
    });

    // 7. Category tree controls, with state kept in sync for assistive technology.
    var treeContainer = document.querySelector(".w2a-tree-container");
    if (treeContainer) {
      function setTreeNodeExpanded(node, expanded) {
        node.classList.toggle("expanded", expanded);
        var toggle = node.querySelector(":scope > .w2a-tree-item .w2a-tree-toggle");
        var children = node.querySelector(":scope > .w2a-tree-sub-list");
        if (toggle) toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
        if (children) children.hidden = !expanded;
      }

      treeContainer.addEventListener("click", function (e) {
        var toggleBtn = e.target.closest(".w2a-tree-toggle");
        if (!toggleBtn) return;

        e.preventDefault();
        var node = toggleBtn.closest(".w2a-tree-node");
        if (node) setTreeNodeExpanded(node, !node.classList.contains("expanded"));
      });

      var expandAllBtn = document.getElementById("w2a_tree_expand_all");
      var collapseAllBtn = document.getElementById("w2a_tree_collapse_all");
      var branchNodes = treeContainer.querySelectorAll(".w2a-tree-node.has-children");

      if (expandAllBtn) {
        expandAllBtn.addEventListener("click", function () {
          branchNodes.forEach(function (node) {
            setTreeNodeExpanded(node, true);
          });
        });
      }

      if (collapseAllBtn) {
        collapseAllBtn.addEventListener("click", function () {
          branchNodes.forEach(function (node) {
            setTreeNodeExpanded(node, false);
          });
        });
      }

      var treeSearchInput = document.getElementById("w2a_tree_search_input");
      var treeClearBtn = document.getElementById("w2a_tree_search_clear");
      if (treeSearchInput) {
        treeSearchInput.addEventListener("input", function () {
          var query = treeSearchInput.value.trim().toLocaleLowerCase("ar");
          if (treeClearBtn) treeClearBtn.hidden = query.length === 0;

          var allNodes = Array.from(treeContainer.querySelectorAll(".w2a-tree-node"));
          allNodes.forEach(function (node) {
            node.classList.remove("search-hidden", "search-matched");
          });

          if (!query) {
            branchNodes.forEach(function (node) {
              setTreeNodeExpanded(node, false);
            });
            return;
          }

          allNodes.forEach(function (node) {
            var title = (node.getAttribute("data-title") || "").toLocaleLowerCase("ar");
            node.classList.toggle("search-hidden", title.indexOf(query) === -1);
            node.classList.toggle("search-matched", title.indexOf(query) !== -1);
          });

          allNodes.filter(function (node) {
            return node.classList.contains("search-matched");
          }).forEach(function (node) {
            var parent = node.parentElement ? node.parentElement.closest(".w2a-tree-node") : null;
            while (parent) {
              parent.classList.remove("search-hidden");
              setTreeNodeExpanded(parent, true);
              parent = parent.parentElement ? parent.parentElement.closest(".w2a-tree-node") : null;
            }
          });
        });

        if (treeClearBtn) {
          treeClearBtn.addEventListener("click", function () {
            treeSearchInput.value = "";
            treeSearchInput.dispatchEvent(new Event("input"));
            treeSearchInput.focus();
          });
        }
      }
    }

    // 8. Searchable alphabetical preacher directory.
    var preachersWrap = document.querySelector(".w2a-preachers-wrap");
    if (preachersWrap) {
      var authorSearchInput = document.getElementById("w2a_author_search_input");
      var authorClearBtn = document.getElementById("w2a_author_search_clear");
      var authorResultStatus = document.getElementById("w2a_author_result_status");

      if (authorSearchInput) {
        authorSearchInput.addEventListener("input", function () {
          var query = authorSearchInput.value.trim().toLocaleLowerCase("ar");
          var visibleTotal = 0;
          if (authorClearBtn) authorClearBtn.hidden = query.length === 0;

          preachersWrap.querySelectorAll(".w2a-letter-section").forEach(function (section) {
            var sectionVisible = 0;
            section.querySelectorAll(".w2a-preacher-card").forEach(function (card) {
              var name = (card.getAttribute("data-name") || "").toLocaleLowerCase("ar");
              var visible = !query || name.indexOf(query) !== -1;
              card.classList.toggle("search-hidden", !visible);
              if (visible) sectionVisible += 1;
            });
            section.classList.toggle("search-hidden", sectionVisible === 0);
            visibleTotal += sectionVisible;
          });

          if (authorResultStatus) {
            authorResultStatus.textContent = visibleTotal + " نتيجة";
          }
        });

        if (authorClearBtn) {
          authorClearBtn.addEventListener("click", function () {
            authorSearchInput.value = "";
            authorSearchInput.dispatchEvent(new Event("input"));
            authorSearchInput.focus();
          });
        }
      }

      preachersWrap.querySelectorAll(".w2a-alphabet-link").forEach(function (link) {
        link.addEventListener("click", function (e) {
          var target = document.querySelector(link.getAttribute("href"));
          if (!target) return;
          e.preventDefault();
          target.scrollIntoView({ behavior: prefersReducedMotion ? "auto" : "smooth" });
          target.focus({ preventScroll: true });
        });
      });
    }

    // 9. Satellite-channel search.
    var channelsWrap = document.querySelector(".w2a-channels-wrap");
    if (channelsWrap) {
      var channelSearchInput = document.getElementById("w2a_channel_search_input");
      var channelClearBtn = document.getElementById("w2a_channel_search_clear");
      var channelResultStatus = document.getElementById("w2a_channel_result_status");

      if (channelSearchInput) {
        channelSearchInput.addEventListener("input", function () {
          var query = channelSearchInput.value.trim().toLocaleLowerCase("ar");
          var visibleTotal = 0;
          if (channelClearBtn) channelClearBtn.hidden = query.length === 0;

          channelsWrap.querySelectorAll(".w2a-channel-card").forEach(function (card) {
            var title = (card.getAttribute("data-title") || "").toLocaleLowerCase("ar");
            var frequency = (card.getAttribute("data-freq") || "").toLocaleLowerCase("ar");
            var visible = !query || title.indexOf(query) !== -1 || frequency.indexOf(query) !== -1;
            card.classList.toggle("search-hidden", !visible);
            if (visible) visibleTotal += 1;
          });

          if (channelResultStatus) channelResultStatus.textContent = visibleTotal + " نتيجة";
        });

        if (channelClearBtn) {
          channelClearBtn.addEventListener("click", function () {
            channelSearchInput.value = "";
            channelSearchInput.dispatchEvent(new Event("input"));
            channelSearchInput.focus();
          });
        }
      }
    }

    // 10. Quran-reciter search.
    var recitersWrap = document.querySelector(".w2a-reciters-wrap");
    if (recitersWrap) {
      var reciterSearchInput = document.getElementById("w2a_reciter_search_input");
      var reciterClearBtn = document.getElementById("w2a_reciter_search_clear");
      var reciterResultStatus = document.getElementById("w2a_reciter_result_status");

      if (reciterSearchInput) {
        reciterSearchInput.addEventListener("input", function () {
          var query = reciterSearchInput.value.trim().toLocaleLowerCase("ar");
          var visibleTotal = 0;
          if (reciterClearBtn) reciterClearBtn.hidden = query.length === 0;

          recitersWrap.querySelectorAll(".w2a-reciter-card").forEach(function (card) {
            var title = (card.getAttribute("data-title") || "").toLocaleLowerCase("ar");
            var visible = !query || title.indexOf(query) !== -1;
            card.classList.toggle("search-hidden", !visible);
            if (visible) visibleTotal += 1;
          });

          if (reciterResultStatus) reciterResultStatus.textContent = visibleTotal + " نتيجة";
        });

        if (reciterClearBtn) {
          reciterClearBtn.addEventListener("click", function () {
            reciterSearchInput.value = "";
            reciterSearchInput.dispatchEvent(new Event("input"));
            reciterSearchInput.focus();
          });
        }
      }
    }

    // Keep the mobile menu button state available to assistive technology.
    var mobileToggler = document.querySelector(".mobi-toggler");
    if (mobileToggler) {
      mobileToggler.addEventListener("click", function () {
        var expanded = mobileToggler.getAttribute("aria-expanded") === "true";
        mobileToggler.setAttribute("aria-expanded", expanded ? "false" : "true");
      });
    }
  });
})();
