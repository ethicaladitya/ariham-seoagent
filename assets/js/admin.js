/**
 * SEO Agent AI — Admin JS v5.0
 * Interactions, AJAX helpers, animations, toast notifications.
 */
/* global seoAgentAI, ajaxurl */
( function () {
	'use strict';

	var cfg = ( typeof seoAgentAI !== 'undefined' ) ? seoAgentAI : {};
	var ajaxUrl = cfg.ajaxUrl || ( typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php' );

	// -------------------------------------------------------------------------
	// Toast notifications
	// -------------------------------------------------------------------------
	var toastContainer;

	function getToastContainer() {
		if ( ! toastContainer ) {
			toastContainer = document.createElement( 'div' );
			toastContainer.id = 'sai-toasts';
			document.body.appendChild( toastContainer );
		}
		return toastContainer;
	}

	function toast( message, type, duration ) {
		type     = type     || 'info';
		duration = duration || 3500;

		var icons = {
			success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
			error:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>',
			info:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>',
		};

		var el = document.createElement( 'div' );
		el.className = 'sai-toast toast-' + type;
		el.innerHTML = ( icons[ type ] || icons.info ) + '<span>' + escHtml( message ) + '</span>';

		getToastContainer().appendChild( el );

		setTimeout( function () {
			el.classList.add( 'hiding' );
			setTimeout( function () { el.parentNode && el.parentNode.removeChild( el ); }, 220 );
		}, duration );
	}

	function escHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	// -------------------------------------------------------------------------
	// Button loading state helpers
	// -------------------------------------------------------------------------
	function btnLoading( btn ) {
		btn.dataset.originalText = btn.innerHTML;
		btn.disabled = true;
		btn.classList.add( 'loading' );
		var label = btn.querySelector( '.btn-label' );
		if ( label ) {
			btn.dataset.originalLabel = label.textContent;
			label.textContent = cfg.i18n && cfg.i18n.loading ? cfg.i18n.loading : 'Working…';
		}
	}

	function btnReset( btn ) {
		btn.disabled = false;
		btn.classList.remove( 'loading' );
		if ( btn.dataset.originalLabel ) {
			var label = btn.querySelector( '.btn-label' );
			if ( label ) { label.textContent = btn.dataset.originalLabel; }
		}
	}

	// -------------------------------------------------------------------------
	// Score ring animator
	// -------------------------------------------------------------------------
	function animateRings() {
		document.querySelectorAll( '.sai-score-ring[data-score]' ).forEach( function ( ring ) {
			var score  = parseInt( ring.dataset.score, 10 ) || 0;
			var fill   = ring.querySelector( '.ring-fill' );
			var numEl  = ring.querySelector( '.sai-score-ring-num' );
			if ( ! fill ) { return; }

			var r          = parseFloat( fill.getAttribute( 'r' ) ) || 40;
			var circ       = 2 * Math.PI * r;
			var dashOffset = circ - ( circ * score / 100 );

			requestAnimationFrame( function () {
				fill.style.strokeDasharray  = circ;
				fill.style.strokeDashoffset = circ;
				requestAnimationFrame( function () {
					fill.style.transition       = 'stroke-dashoffset .9s cubic-bezier(0.4,0,0.2,1)';
					fill.style.strokeDashoffset = dashOffset;
				} );
			} );

			if ( numEl ) {
				var dur = 900;
				var t0  = performance.now();
				(function tick( now ) {
					var progress = Math.min( ( now - t0 ) / dur, 1 );
					var eased    = 1 - Math.pow( 1 - progress, 3 );
					numEl.textContent = Math.round( eased * score );
					if ( progress < 1 ) { requestAnimationFrame( tick ); }
				})( performance.now() );
			}
		} );
	}

	// Animate score distribution bars (start at 0%, animate to data-w).
	function animateBars() {
		document.querySelectorAll( '.sai-score-bar-fill[data-w], .sai-dist-bar-fill[data-w]' ).forEach( function ( bar ) {
			var w = bar.dataset.w || '0';
			bar.style.width = '0%';
			requestAnimationFrame( function () {
				requestAnimationFrame( function () { bar.style.width = w + '%'; } );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Async batch scan (dashboard + opportunities)
	// -------------------------------------------------------------------------
	function initScan() {
		var btns   = document.querySelectorAll( '.sai-run-scan' );
		var prog   = document.querySelector( '.sai-scan-progress' );
		var bar    = prog && prog.querySelector( '.sai-scan-bar-fill' );
		var status = prog && prog.querySelector( '.sai-scan-status' );
		if ( ! btns.length ) { return; }

		var i18n = cfg.i18n || {};

		function setScanning( active ) {
			btns.forEach( function ( b ) {
				if ( active ) { btnLoading( b ); } else { btnReset( b ); }
			} );
			if ( prog ) {
				if ( active ) {
					prog.style.display = 'block';
					prog.classList.add( 'visible' );
				} else {
					prog.classList.remove( 'visible' );
				}
			}
		}

		function updateProgress( pct, text ) {
			if ( bar )    { bar.style.width = pct + '%'; }
			if ( status ) { status.textContent = text; }
		}

		function runBatch( offset ) {
			var fd = new FormData();
			fd.append( 'action',      'ariham_seoagent_analyze_batch' );
			fd.append( '_ajax_nonce', cfg.nonce || '' );
			fd.append( 'offset',      offset );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res.success ) {
						toast( res.data || ( i18n.scan_error || 'Scan failed. Please try again.' ), 'error' );
						setScanning( false );
						return;
					}
					var d   = res.data;
					var pct = d.percent || 0;
					var msg = d.done
						? ( i18n.scan_done || 'Scan complete! ' ) + d.with_recs + ' ' + ( i18n.recommendations || 'recommendation(s) generated.' )
						: ( i18n.scanning  || 'Scanning' ) + ' ' + pct + '%' + ( d.current_title ? ' — ' + d.current_title : '' );
					updateProgress( pct, msg );

					if ( d.done ) {
						if ( bar ) { bar.classList.add( 'complete' ); }
						toast( ( i18n.scan_done || 'Scan complete!' ) + ' ' + d.with_recs + ' ' + ( i18n.recommendations || 'recommendations generated.' ), 'success', 5000 );
						setTimeout( function () { window.location.reload(); }, 2000 );
					} else {
						runBatch( d.processed );
					}
				} )
				.catch( function () {
					toast( i18n.network_error || 'Network error. Please try again.', 'error' );
					setScanning( false );
				} );
		}

		btns.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				setScanning( true );
				updateProgress( 0, i18n.starting || 'Starting scan…' );
				runBatch( 0 );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Decision approve / reject (AJAX — only when no inline form is present)
	// -------------------------------------------------------------------------
	function initDecisionActions() {
		if ( document.getElementById( 'sai-decision-form' ) ) { return; }

		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-decision-action]' );
			if ( ! btn ) { return; }

			var action     = btn.dataset.decisionAction;
			var decisionId = btn.dataset.decisionId;
			if ( ! decisionId ) { return; }

			btnLoading( btn );

			var fd = new FormData();
			fd.append( 'action',          'ariham_seoagent_decision_action' );
			fd.append( '_ajax_nonce',     cfg.nonce || '' );
			fd.append( 'decision_id',     decisionId );
			fd.append( 'decision_action', action );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res.success ) {
						toast( res.data || 'Action failed.', 'error' );
						btnReset( btn );
						return;
					}
					var card = btn.closest( '.sai-decision' );
					if ( card ) {
						card.style.transition = 'opacity .28s ease, transform .28s ease, max-height .28s ease';
						card.style.opacity    = '0';
						card.style.transform  = 'translateX(-12px)';
						setTimeout( function () { card.remove(); updateDecisionCount(); }, 300 );
					}
					toast( res.data || ( 'approve' === action ? 'Applied!' : 'Discarded.' ), 'success' );
				} )
				.catch( function () {
					toast( 'Network error. Please try again.', 'error' );
					btnReset( btn );
				} );
		} );
	}

	function updateDecisionCount() {
		var remaining = document.querySelectorAll( '.sai-decision' ).length;
		var counter   = document.querySelector( '.sai-pending-count' );
		if ( counter ) { counter.textContent = remaining; }
		if ( 0 === remaining ) {
			var list = document.querySelector( '.sai-decision-list' );
			if ( list ) {
				list.innerHTML = '<div class="sai-empty"><div class="sai-empty-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><h3>All caught up!</h3><p>No pending decisions at this time. The agent will surface new ones on the next analysis run.</p></div>';
			}
		}
	}

	// -------------------------------------------------------------------------
	// Collapsible decision cards — expand/collapse diff on header click
	// CSS drives the expand indicator arrow via ::after + .expanded class.
	// -------------------------------------------------------------------------
	function initDecisionExpand() {
		if ( document.getElementById( 'sai-decision-form' ) ) { return; }

		document.addEventListener( 'click', function ( e ) {
			var header = e.target.closest( '.sai-decision-header' );
			if ( ! header ) { return; }
			if ( e.target.closest( 'button, a' ) ) { return; }

			var card = header.closest( '.sai-decision' );
			var body = card && card.querySelector( '.sai-decision-body' );
			if ( ! body ) { return; }

			var open = card.classList.toggle( 'expanded' );
			body.style.display = open ? 'block' : 'none';
		} );
	}

	// -------------------------------------------------------------------------
	// Bulk approve-safe decisions
	// -------------------------------------------------------------------------
	function initBulkSafe() {
		var btn = document.querySelector( '.sai-bulk-apply-safe' );
		if ( ! btn ) { return; }

		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( cfg.i18n && cfg.i18n.bulk_confirm ? cfg.i18n.bulk_confirm : 'Apply all safe pending decisions now? This cannot be undone.' ) ) { return; }
			btnLoading( btn );

			var fd = new FormData();
			fd.append( 'action',      'ariham_seoagent_bulk_apply_safe' );
			fd.append( '_ajax_nonce', cfg.nonceApprove || cfg.nonce || '' );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res.success ) {
						toast( res.data || 'Bulk apply failed.', 'error' );
						btnReset( btn );
						return;
					}
					toast( res.data || 'All safe decisions applied!', 'success', 5000 );
					setTimeout( function () { window.location.reload(); }, 1500 );
				} )
				.catch( function () {
					toast( 'Network error.', 'error' );
					btnReset( btn );
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Key field reveal toggle (settings page)
	// -------------------------------------------------------------------------
	function initKeyReveal() {
		document.querySelectorAll( '.sai-key-reveal' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var wrap  = btn.closest( '.sai-key-input-wrap' );
				var input = wrap && wrap.querySelector( 'input' );
				if ( ! input ) { return; }

				var hidden = 'password' === input.type;
				input.type = hidden ? 'text' : 'password';
				btn.setAttribute( 'aria-label', hidden ? 'Hide key' : 'Show key' );
				btn.innerHTML = hidden
					? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.944-2.66-9.424-6.41a1.651 1.651 0 010-1.185A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>'
					: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>';
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Cron "trigger now" buttons
	// -------------------------------------------------------------------------
	function initCronTrigger() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-cron-hook]' );
			if ( ! btn ) { return; }

			var hook = btn.dataset.cronHook;
			btnLoading( btn );

			var fd = new FormData();
			fd.append( 'action',      'ariham_seoagent_trigger_cron' );
			fd.append( '_ajax_nonce', cfg.nonce || '' );
			fd.append( 'hook',        hook );

			fetch( ajaxUrl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					btnReset( btn );
					if ( res.success ) {
						toast( 'Job queued — will run momentarily.', 'success' );
					} else {
						toast( res.data || 'Could not trigger job.', 'error' );
					}
				} )
				.catch( function () {
					btnReset( btn );
					toast( 'Network error.', 'error' );
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Autopilot toggle (settings page — live visual update)
	// -------------------------------------------------------------------------
	function initAutopilotToggle() {
		var toggle = document.querySelector( '[data-autopilot-toggle]' );
		if ( ! toggle ) { return; }

		toggle.addEventListener( 'change', function () {
			var bar   = document.querySelector( '.sai-autopilot-bar' );
			var label = document.querySelector( '.sai-ap-label' );
			var on    = toggle.checked;

			if ( bar ) {
				bar.classList.toggle( 'ap-on',  on );
				bar.classList.toggle( 'ap-off', ! on );
			}
			if ( label ) { label.textContent = on ? 'Autopilot ON' : 'Autopilot OFF'; }
		} );
	}

	// -------------------------------------------------------------------------
	// Redirect delete confirmation
	// -------------------------------------------------------------------------
	function initRedirectDelete() {
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.sai-delete-redirect' );
			if ( ! link ) { return; }
			if ( ! window.confirm( 'Delete this redirect? This cannot be undone.' ) ) {
				e.preventDefault();
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Rollback confirm
	// -------------------------------------------------------------------------
	function initRollback() {
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.sai-rollback-btn' );
			if ( ! btn ) { return; }
			if ( ! window.confirm( 'Roll back this change? The original value will be restored.' ) ) {
				e.preventDefault();
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Sticky header — add class on scroll for CSS shadow transition
	// -------------------------------------------------------------------------
	function initStickyHeader() {
		var header = document.querySelector( '.sai-header' );
		if ( ! header ) { return; }

		function onScroll() {
			header.classList.toggle( 'scrolled', window.pageYOffset > 50 );
		}

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll();
	}

	// -------------------------------------------------------------------------
	// Intersection observer — reveal metric cards on scroll
	// Decision cards and timeline items use CSS animation-delay instead.
	// -------------------------------------------------------------------------
	function initScrollAnimations() {
		if ( ! window.IntersectionObserver ) { return; }

		var items = document.querySelectorAll( '.sai-metric, .sai-cron-job' );
		var io    = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.style.opacity   = '1';
					entry.target.style.transform = 'translateY(0)';
					io.unobserve( entry.target );
				}
			} );
		}, { threshold: 0.08 } );

		items.forEach( function ( el, i ) {
			el.style.opacity    = '0';
			el.style.transform  = 'translateY(10px)';
			el.style.transition = 'opacity .32s ease ' + ( i * 45 ) + 'ms, transform .32s ease ' + ( i * 45 ) + 'ms';
			io.observe( el );
		} );
	}

	// -------------------------------------------------------------------------
	// Close WP admin notices on plugin pages (use toasts instead)
	// -------------------------------------------------------------------------
	function suppressWPNotices() {
		if ( ! document.querySelector( '.sai-page' ) ) { return; }
		document.querySelectorAll( '.notice-success, .updated' ).forEach( function ( n ) {
			if ( ! n.closest( '.sai-page' ) ) { n.style.display = 'none'; }
		} );
	}

	// -------------------------------------------------------------------------
	// Auto-dismiss URL-based success / error notices
	// -------------------------------------------------------------------------
	function initUrlNotice() {
		var url = new URL( window.location.href );
		if ( url.searchParams.get( 'sai_saved' ) ) {
			toast( cfg.i18n && cfg.i18n.saved ? cfg.i18n.saved : 'Settings saved!', 'success' );
			url.searchParams.delete( 'sai_saved' );
			window.history.replaceState( {}, '', url.toString() );
		}
		if ( url.searchParams.get( 'sai_error' ) ) {
			toast( decodeURIComponent( url.searchParams.get( 'sai_error' ) || '' ), 'error' );
			url.searchParams.delete( 'sai_error' );
			window.history.replaceState( {}, '', url.toString() );
		}
	}

	// -------------------------------------------------------------------------
	// Init
	// -------------------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		animateRings();
		animateBars();
		initScan();
		initDecisionActions();
		initDecisionExpand();
		initBulkSafe();
		initKeyReveal();
		initCronTrigger();
		initAutopilotToggle();
		initRedirectDelete();
		initRollback();
		initStickyHeader();
		initScrollAnimations();
		suppressWPNotices();
		initUrlNotice();

		// Collapse all decision bodies by default.
		document.querySelectorAll( '.sai-decision-body' ).forEach( function ( body ) {
			body.style.display = 'none';
		} );
	} );

	// Expose toast globally so PHP-rendered inline scripts can use it.
	window.saiToast = toast;

} )();
