if ("undefined" == typeof _dumpperInitialized) {
    const s = {
        t: [],
        i: -(_dumpperInitialized = 1),
        o: function (e) {
            const t = window.getSelection(),
                n = document.createRange();
            n.selectNodeContents(e), t.removeAllRanges(), t.addRange(n);
        },
        u: function (e, t) {
            Array.prototype.slice.call(document.querySelectorAll(e), 0).forEach(t);
        },
        l: function (e, t) {
            return !!e.classList && e.classList.contains((t = void 0 === t ? "_dumpper-show" : t));
        },
        g: function (e, t) {
            e.classList.add((t = void 0 === t ? "_dumpper-show" : t));
        },
        v: function (e, t) {
            return e.classList.remove((t = void 0 === t ? "_dumpper-show" : t)), e;
        },
        next: function (e) {
            for (; (e = e.nextElementSibling) && "DD" !== e.tagName; );
            return e;
        },
        panel: function (e) {
            return "FOOTER" === e.tagName ? e.querySelector("ol") : s.next(e);
        },
        resetPanel: function (panel) {
            if (!panel) return;
            if (panel._dumpperEnd) {
                panel.removeEventListener("transitionend", panel._dumpperEnd);
                panel._dumpperEnd = null;
            }
            panel._dumpperAnimating = false;
        },
        finalizePanel: function (panel, hidden) {
            s.resetPanel(panel);
            panel.style.transition = "";
            if (hidden) {
                panel.style.display = "none";
                panel.style.maxHeight = "";
                panel.style.opacity = "";
                panel.style.overflow = "";
            } else {
                panel.style.display = "block";
                panel.style.maxHeight = "none";
                panel.style.opacity = "1";
                panel.style.overflow = "";
            }
        },
        expand: function (e) {
            const panel = s.panel(e);
            if (!panel || s.l(e)) return e;
            if (panel._dumpperAnimating) return e;
            s.g(e);
            s.resetPanel(panel);
            panel._dumpperAnimating = true;
            panel.style.display = "block";
            panel.style.overflow = "hidden";
            panel.style.maxHeight = "0";
            panel.style.opacity = "0";
            panel.style.transition = "max-height .15s ease";
            panel.offsetHeight;
            panel.style.maxHeight = panel.scrollHeight + "px";
            panel._dumpperEnd = function (ev) {
                if (ev.target !== panel || "max-height" !== ev.propertyName) return;
                s.resetPanel(panel);
                panel._dumpperAnimating = true;
                panel.style.maxHeight = "none";
                panel.style.overflow = "hidden";
                panel.style.transition = "opacity .12s ease";
                panel.offsetHeight;
                panel.style.opacity = "1";
                panel._dumpperEnd = function (ev2) {
                    if (ev2.target !== panel || "opacity" !== ev2.propertyName) return;
                    s.finalizePanel(panel, false);
                };
                panel.addEventListener("transitionend", panel._dumpperEnd);
            };
            panel.addEventListener("transitionend", panel._dumpperEnd);
            return e;
        },
        collapse: function (e) {
            const panel = s.panel(e);
            if (!panel || !s.l(e)) return e;
            if (panel._dumpperAnimating) return e;
            s.resetPanel(panel);
            panel._dumpperAnimating = true;
            panel.style.display = "block";
            panel.style.overflow = "hidden";
            panel.style.maxHeight = panel.scrollHeight + "px";
            panel.style.opacity = "1";
            panel.style.transition = "opacity .1s ease, max-height .14s ease .08s";
            panel.offsetHeight;
            panel.style.opacity = "0";
            panel.style.maxHeight = "0";
            panel._dumpperEnd = function (ev) {
                if (ev.target !== panel || "max-height" !== ev.propertyName) return;
                s.v(e);
                s.finalizePanel(panel, true);
            };
            panel.addEventListener("transitionend", panel._dumpperEnd);
            return e;
        },
        toggle: function (e, wasOpen) {
            wasOpen = void 0 === wasOpen ? s.l(e) : wasOpen;
            wasOpen ? s.collapse(e) : s.expand(e);
            let n = s.next(e);
            n && 1 === n.childNodes.length && (n = n.childNodes[0].childNodes[0]) && s.l(n, "_dumpper-parent") && s.toggle(n, wasOpen);
            return e;
        },
        _: function (e, t) {
            void 0 === t && (t = s.l(e));
            const n = s.next(e);
            if (n) {
                const i = n.getElementsByClassName("_dumpper-parent");
                for (let o = i.length; o--; ) s.toggle(i[o], t);
            }
            s.toggle(e, t);
        },
        p: function (e) {
            var t = document.getElementsByClassName("_dumpper-parent");
            let n = t.length;
            for (var i = s.l(e.parentNode); n--; ) s.toggle(t[n], i);
        },
        h: function (e) {
            let t,
                n = e,
                i = 0;
            for (s.v(e.parentNode.getElementsByClassName("_dumpper-active-tab")[0], "_dumpper-active-tab"), e.className = "_dumpper-active-tab"; (n = n.previousSibling); ) 1 === n.nodeType && i++;
            t = e.parentNode.nextSibling.childNodes;
            for (let e = 0; e < t.length; e++) t[e].style.display = e === i ? "block" : "none";
        },
        m: function (e) {
            for (; (e = e.parentNode) && !s.l(e, "_dumpper"); );
            return !!e;
        },
        root: function (e) {
            for (; e && !s.l(e, "_dumpper"); ) e = e.parentNode;
            return e || null;
        },
        close: function (e) {
            if (!e || s.l(e, "_dumpper-closing")) return;
            s.g(e, "_dumpper-closing");
            if (-1 !== s.i && s.t[s.i] && s.root(s.t[s.i]) === e) s.R.C(-1);
            const t = function (n) {
                n.target === e &&
                    "opacity" === n.propertyName &&
                    (e.removeEventListener("transitionend", t), e.remove(), -1 !== s.i && s.T());
            };
            e.addEventListener("transitionend", t);
            setTimeout(function () {
                e.parentNode && e.remove();
            }, 400);
        },
        T: function () {
            (s.t = []),
                s.u("._dumpper nav, ._dumpper-tabs>li:not(._dumpper-active-tab)", function (e) {
                    (0 === e.offsetWidth && 0 === e.offsetHeight) || s.t.push(e);
                });
        },
        tag: function (e) {
            return "<" + e + ">";
        },
        A: function (e) {
            let t;
            (t = window.open()) &&
                (t.document.open(),
                t.document.write(
                    s.tag("html") +
                        s.tag("head") +
                        "<title>Dump ☯ (" +
                        new Date().toISOString() +
                        ")</title>" +
                        s.tag('meta charset="utf-8"') +
                        document.getElementsByClassName("_dumpper-js")[0].outerHTML +
                        document.getElementsByClassName("_dumpper-css")[0].outerHTML +
                        s.tag("/head") +
                        s.tag("body") +
                        '<input style="width: 100%" placeholder="Take some notes!"><div class="_dumpper">' +
                        e.parentNode.outerHTML +
                        "</div>" +
                        s.tag("/body")
                ),
                t.document.close());
        },
        I: function (e, n, t) {
            const i = e.tBodies[0],
                o = new Intl.Collator(void 0, { numeric: !0, sensitivity: "base" }),
                dir = void 0 === t.k ? 1 : t.k;
            (t.k = -1 * dir),
                []
                    .slice.call(e.tBodies[0].rows)
                    .sort(function (e, t) {
                        return dir * o.compare(e.cells[n].textContent, t.cells[n].textContent);
                    })
                    .forEach(function (e) {
                        i.appendChild(e);
                    });
        },
        R: {
            C: function (e) {
                var t = "_dumpper-focused",
                    n = document.querySelector("." + t);
                if ((n && s.v(n, t), -1 !== e)) {
                    n = s.t[e];
                    s.g(n, t);
                    const i = function (e) {
                        return e.offsetTop + (e.offsetParent ? i(e.offsetParent) : 0);
                    };
                    (t = i(n) - window.innerHeight / 2), window.scrollTo(0, t);
                }
                s.i = e;
            },
            D: function (e, t) {
                return e ? --t < 0 && (t = s.t.length - 1) : ++t >= s.t.length && (t = 0), s.R.C(t), !1;
            },
        },
    };
    window.addEventListener(
        "click",
        function (e) {
            let t = e.target,
                n = t.tagName;
            if (s.l(t, "_dumpper-close")) {
                e.stopPropagation(), e.preventDefault();
                const n = s.root(t);
                return n && s.close(n), !1;
            }
            if (s.m(t)) {
                if ("DFN" === n) s.o(t), (t = t.parentNode);
                else if ("VAR" === n) (t = t.parentNode), (n = t.tagName);
                else if ("TH" === n) return e.ctrlKey || s.I(t.parentNode.parentNode.parentNode, t.cellIndex, t), !1;
                if ("LI" === n && "_dumpper-tabs" === t.parentNode.className)
                    return "_dumpper-active-tab" !== t.className && (s.h(t), -1 !== s.i && s.T()), !1;
                if ("NAV" === n)
                    return "FOOTER" === t.parentNode.tagName
                        ? ((t = t.parentNode), s.toggle(t))
                        : setTimeout(function () {
                              0 < parseInt(t.F, 10) ? t.F-- : (s._(t.parentNode), -1 !== s.i && s.T());
                          }, 300),
                        e.stopPropagation(),
                        !1;
                if (s.l(t, "_dumpper-parent")) return s.toggle(t), -1 !== s.i && s.T(), !1;
                if (s.l(t, "_dumpper-ide-link")) {
                    e.preventDefault();
                    const i = new XMLHttpRequest();
                    i.open("get", t.href), i.send();
                } else if (s.l(t, "_dumpper-popup-trigger")) {
                    let e = t.parentNode;
                    if ("FOOTER" === e.tagName) e = e.previousSibling;
                    else for (; e && !s.l(e, "_dumpper-parent"); ) e = e.parentNode;
                    s.A(e);
                } else "PRE" === n && 3 === e.detail && s.o(t);
            }
        },
        !1
    ),
        window.addEventListener(
            "dblclick",
            function (e) {
                const t = e.target;
                s.m(t) && "NAV" === t.tagName && ((t.F = 2), s.p(t), -1 !== s.i && s.T(), e.stopPropagation());
            },
            !1
        ),
        (window.onkeydown = function (n) {
            if (!(["INPUT", "TEXTAREA", "BUTTON"].includes(n.target.tagName) || n.altKey || n.ctrlKey)) {
                var i = n.keyCode;
                n.shiftKey;
                let t = s.i;
                if (9 === i) s.R.C(-1);
                else {
                    if (68 === i) return -1 === t ? (s.T(), s.R.D(!1, t)) : (s.R.C(-1), !1);
                    if (-1 !== t) {
                        if (38 === i) return s.R.D(!0, t);
                        if (40 === i) return s.R.D(!1, t);
                        let e = s.t[t];
                        if ("LI" === e.tagName) {
                            if (32 === i || 13 === i) return s.h(e), s.T(), s.R.D(!0, t);
                            if (39 === i) return s.R.D(!1, t);
                            if (37 === i) return s.R.D(!0, t);
                        }
                        if ("FOOTER" === (e = e.parentNode).tagName && (32 === i || 13 === i)) return s.toggle(e), !1;
                        if (32 === i || 13 === i) return s.toggle(e), s.T(), !1;
                        if (39 === i || 37 === i) {
                            n = 37 === i;
                            if (s.l(e)) s._(e, n);
                            else {
                                if (n) {
                                    for (; (e = e.parentNode) && "DD" !== e.tagName; );
                                    if (e) {
                                        (e = e.previousElementSibling), (t = -1);
                                        for (var o = e.querySelector("nav"); o !== s.t[++t]; );
                                        s.R.C(t);
                                    } else e = s.t[t].parentNode;
                                }
                                s.toggle(e, n);
                            }
                            return s.T(), !1;
                        }
                    }
                }
            }
        }),
        window.addEventListener("load", function () {
            const e = Array.prototype.slice.call(document.querySelectorAll("._dumpper-microtime"), 0);
            let n = 1 / 0,
                i = -1 / 0;
            e.forEach(function (e) {
                (e = parseFloat(e.innerHTML)), n > e && (n = e), i < e && (i = e);
            }),
                e.forEach(function (e) {
                    var t = 1 - (parseFloat(e.innerHTML) - n) / (i - n);
                    e.style.background = "hsl(" + Math.round(120 * t) + ",60%,70%)";
                });
        });
}
