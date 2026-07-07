( function () {

	function init() {

		if ( typeof window.LOOKIT_SUCURI === 'undefined' ) {
			return;
		}

		var cfg = window.LOOKIT_SUCURI;

		// Toast
		var toastEl = document.createElement( 'div' );
		toastEl.id = 'lookit-sucuri-toast';
		document.body.appendChild( toastEl );
		var toastTimer = null;

		function showToast( msg, type, duration ) {
			if ( toastTimer ) { clearTimeout( toastTimer ); }
			toastEl.textContent = msg;
			toastEl.className = type;
			void toastEl.offsetWidth;
			toastEl.classList.add( 'lookit-sucuri-toast-show' );
			toastTimer = setTimeout( function () {
				toastEl.classList.remove( 'lookit-sucuri-toast-show' );
			}, duration || 5000 );
		}

		// ── Purge This URL ─────────────────────────────────────────────────
		var urlNode = document.getElementById( 'wp-admin-bar-lookit-sucuri-purge-url' );
		if ( urlNode ) {
			var urlLink = urlNode.querySelector( 'a' ) || urlNode;
			urlLink.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( urlNode.classList.contains( 'lookit-sucuri-purging' ) ) { return; }
				if ( ! cfg.currentUrl ) { return; }
				if ( ! confirm( 'Purge this URL from Sucuri edge cache?\n\n' + cfg.currentUrl ) ) { return; }
				doPurge( 'lookit_sucuri_purge_url', cfg.currentUrl, urlNode, urlLink, 'Purge This URL', cfg.nonceUrl );
			} );
		}

		// ── Purge Entire Site ──────────────────────────────────────────────
		var allNode = document.getElementById( 'wp-admin-bar-lookit-sucuri-purge-all' );
		if ( allNode ) {
			var allLink = allNode.querySelector( 'a' ) || allNode;
			allLink.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				if ( allNode.classList.contains( 'lookit-sucuri-purging' ) ) { return; }
				if ( ! confirm( 'Purge the ENTIRE Sucuri cache for this site?\n\nThis will temporarily slow the site as pages re-cache.' ) ) { return; }
				doPurge( 'lookit_sucuri_purge_all', null, allNode, allLink, 'Purge Entire Site', cfg.nonceAll );
			} );
		}

		// ── Helpers ────────────────────────────────────────────────────────
		function doPurge( action, url, node, btn, resetLabel, nonce ) {
			node.classList.add( 'lookit-sucuri-purging' );
			if ( btn ) { btn.textContent = 'Purging…'; }
			doAjax( action, url, nonce, function ( ok, msg, isRateLimit ) {
				node.classList.remove( 'lookit-sucuri-purging' );
				if ( ok ) {
					node.classList.add( 'lookit-sucuri-success' );
					if ( btn ) { btn.textContent = '✅ Sent'; }
					var successMsg = ( action === 'lookit_sucuri_purge_all' )
						? '✅ Full site purge sent. Please wait up to 2 minutes for changes to take effect on Sucuri\'s edge cache.'
						: '✅ URL purge sent. Please wait up to 2 minutes for the change to take effect on Sucuri\'s edge cache.';
					showToast( successMsg, 'success', 7000 );
				} else {
					node.classList.add( 'lookit-sucuri-error' );
					if ( btn ) { btn.textContent = isRateLimit ? '⚠️ Wait' : '❌ Failed'; }
					var toastType = isRateLimit ? 'warning' : 'error';
					showToast( msg, toastType, 7000 );
				}
				setTimeout( function () {
					node.classList.remove( 'lookit-sucuri-success', 'lookit-sucuri-error' );
					if ( btn ) { btn.textContent = resetLabel; }
				}, 5000 );
			} );
		}

		function doAjax( action, url, nonce, callback ) {
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', cfg.ajaxUrl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.onreadystatechange = function () {
				if ( xhr.readyState !== 4 ) { return; }
				try {
					var res = JSON.parse( xhr.responseText );
					var msg = '';
					var isRateLimit = false;
					if ( res.success ) {
						msg = res.data || '';
					} else if ( res.data && typeof res.data === 'object' ) {
						msg = res.data.message || 'Unknown error';
						isRateLimit = !! res.data.rate_limited;
					} else {
						msg = res.data || 'Unknown error';
					}
					callback( res.success, msg, isRateLimit );
				} catch ( err ) {
					callback( false, 'Unexpected response from server', false );
				}
			};
			var body = 'action=' + encodeURIComponent( action ) +
			           '&nonce=' + encodeURIComponent( nonce );
			if ( url ) { body += '&url=' + encodeURIComponent( url ); }
			xhr.send( body );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
