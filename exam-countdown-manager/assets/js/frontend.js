/**
 * 資格試験カウントダウンマネージャー - フロントエンドJavaScript（一列表示対応修正版）
 *
 * @package ExamCountdownManager
 */

(function($) {
    'use strict';

    // カウントダウン管理オブジェクト
    const ExamCountdown = {
        timers: [],
        updateInterval: 1000, // 1秒
        initialized: false,
        
        /**
         * 初期化
         */
        init: function() {
            if (this.initialized) {
                return;
            }
            
            console.log('ECM Frontend: Initializing...');
            
            // DOM要素の存在確認
            const countdownElements = $('.ecm-countdown[data-exam-date]');
            if (countdownElements.length === 0) {
                console.log('ECM Frontend: No countdown elements found');
                return;
            }
            
            console.log('ECM Frontend: Found ' + countdownElements.length + ' countdown elements');
            
            this.initCountdowns();
            this.initResponsive();
            this.initAccessibility();
            this.initAnimations();
            
            // 1分ごとに全体更新
            setInterval(() => {
                this.updateAllCountdowns();
            }, 60000);
            
            this.initialized = true;
            console.log('ECM Frontend: Initialization complete');
        },
        
        /**
         * カウントダウンの初期化
         */
        initCountdowns: function() {
            $('.ecm-countdown[data-exam-date]').each((index, element) => {
                const $countdown = $(element);
                const examDate = $countdown.data('exam-date');
                const showTime = $countdown.data('show-time') === 'true' || $countdown.data('show-time') === true;
                const style = $countdown.data('style') || 'default';
                
                if (!examDate) {
                    console.warn('ECM Frontend: Missing exam-date for countdown element', element);
                    return;
                }
                
                console.log('ECM Frontend: Setting up countdown for ' + examDate + ' (style: ' + style + ')');
                this.setupCountdown($countdown, examDate, showTime, style);
            });
        },
        
        /**
         * 個別カウントダウンのセットアップ
         */
        setupCountdown: function($countdown, examDate, showTime, style) {
            try {
                // 日付解析を改善
                let targetDate;
                if (examDate.includes('T')) {
                    targetDate = new Date(examDate).getTime();
                } else {
                    targetDate = new Date(examDate + ' 00:00:00').getTime();
                }
                
                if (isNaN(targetDate)) {
                    console.error('ECM Frontend: Invalid date format:', examDate);
                    return;
                }
                
                const updateFunction = () => {
                    this.updateSingleCountdown($countdown, targetDate, showTime, style);
                };
                
                // 初回実行
                updateFunction();
                
                // タイマー登録（秒表示またはヘッダー・フッターの場合）
                if (showTime || style === 'header' || style === 'footer') {
                    const timerId = setInterval(updateFunction, this.updateInterval);
                    this.timers.push(timerId);
                    $countdown.data('timer-id', timerId);
                }
                
                console.log('ECM Frontend: Countdown setup complete for', examDate);
            } catch (error) {
                console.error('ECM Frontend: Error setting up countdown:', error);
            }
        },
        
        /**
         * 単一カウントダウンの更新
         */
        updateSingleCountdown: function($countdown, targetDate, showTime, style) {
            try {
                const now = new Date().getTime();
                const distance = targetDate - now;
                
                if (distance < 0) {
                    this.handleExpiredCountdown($countdown);
                    return;
                }
                
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // 表示を更新（スタイルに応じて）
                if (style === 'header' || style === 'footer') {
                    this.updateInlineDisplay($countdown, days, hours, minutes, seconds, showTime);
                } else if (showTime && $countdown.find('.ecm-countdown-detailed').length > 0) {
                    this.updateDetailedDisplay($countdown, days, hours, minutes, seconds);
                } else {
                    this.updateSimpleDisplay($countdown, days);
                }
                
                // 緊急度に応じたスタイル適用
                this.applyUrgencyStyles($countdown, days);
                
                // カスタムイベントを発火
                $countdown.trigger('countdown:updated', {
                    days: days,
                    hours: hours,
                    minutes: minutes,
                    seconds: seconds
                });
                
            } catch (error) {
                console.error('ECM Frontend: Error updating countdown:', error);
            }
        },
        
        /**
         * 一列表示の更新（ヘッダー・フッター用）
         */
        updateInlineDisplay: function($countdown, days, hours, minutes, seconds, showTime) {
            if (showTime) {
                // 詳細時間表示の場合
                const $timeSegments = $countdown.find('.ecm-time-segment .ecm-number');
                if ($timeSegments.length >= 3) {
                    $timeSegments.eq(0).text(days);
                    $timeSegments.eq(1).text(hours);
                    $timeSegments.eq(2).text(minutes);
                }
            } else {
                // シンプル表示の場合
                const $inlineText = $countdown.find('.ecm-inline-text');
                if ($inlineText.length > 0) {
                    const currentValue = parseInt($inlineText.text().match(/\d+/)) || 0;
                    if (currentValue !== days) {
                        $inlineText.addClass('ecm-number-changing');
                        setTimeout(() => {
                            $inlineText.text('あと' + days + '日').removeClass('ecm-number-changing');
                        }, 150);
                    }
                }
            }
        },
        
        /**
         * 詳細表示の更新
         */
        updateDetailedDisplay: function($countdown, days, hours, minutes, seconds) {
            const $timeUnits = $countdown.find('.ecm-time-unit .ecm-number');
            
            if ($timeUnits.length >= 1) {
                this.updateNumberWithAnimation($timeUnits.eq(0), days);
            }
            if ($timeUnits.length >= 2) {
                this.updateNumberWithAnimation($timeUnits.eq(1), hours);
            }
            if ($timeUnits.length >= 3) {
                this.updateNumberWithAnimation($timeUnits.eq(2), minutes);
            }
            if ($timeUnits.length >= 4) {
                this.updateNumberWithAnimation($timeUnits.eq(3), seconds);
            }
        },
        
        /**
         * シンプル表示の更新
         */
        updateSimpleDisplay: function($countdown, days) {
            const $daysNumber = $countdown.find('.ecm-days-number');
            if ($daysNumber.length > 0) {
                this.updateNumberWithAnimation($daysNumber, days);
            }
        },
        
        /**
         * 数値をアニメーション付きで更新
         */
        updateNumberWithAnimation: function($element, newValue) {
            const currentValue = parseInt($element.text()) || 0;
            if (currentValue !== newValue) {
                $element.addClass('ecm-number-changing');
                setTimeout(() => {
                    $element.text(newValue).removeClass('ecm-number-changing');
                }, 150);
            }
        },
        
        /**
         * 期限切れカウントダウンの処理
         */
        handleExpiredCountdown: function($countdown) {
            const timerId = $countdown.data('timer-id');
            if (timerId) {
                clearInterval(timerId);
                this.timers = this.timers.filter(id => id !== timerId);
                $countdown.removeData('timer-id');
            }
            
            // 終了状態のクラスを追加
            $countdown.removeClass('ecm-urgent ecm-very-urgent')
                     .addClass('ecm-countdown-finished');
            
            // コンテンツを更新
            const $content = $countdown.find('.ecm-countdown-detailed, .ecm-countdown-simple, .ecm-countdown-default, .ecm-countdown-compact, .ecm-countdown-inline');
            if ($content.length > 0) {
                $content.html('<div class="ecm-finished-message">試験終了</div>');
            }
            
            // 期限切れイベントを発火
            $countdown.trigger('exam:expired');
            
            console.log('ECM Frontend: Exam expired for countdown element');
        },
        
        /**
         * 緊急度スタイルの適用
         */
        applyUrgencyStyles: function($countdown, days) {
            $countdown.removeClass('ecm-urgent ecm-very-urgent');
            
            if (days <= 3) {
                $countdown.addClass('ecm-very-urgent');
                // 3日以内の場合、緊急通知イベントを発火
                $countdown.trigger('countdown:very-urgent', { days: days });
            } else if (days <= 7) {
                $countdown.addClass('ecm-urgent');
                // 7日以内の場合、注意通知イベントを発火
                $countdown.trigger('countdown:urgent', { days: days });
            }
        },
        
        /**
         * 全カウントダウンの更新
         */
        updateAllCountdowns: function() {
            $('.ecm-countdown[data-exam-date]').each((index, element) => {
                const $countdown = $(element);
                const examDate = $countdown.data('exam-date');
                const showTime = $countdown.data('show-time') === 'true' || $countdown.data('show-time') === true;
                const style = $countdown.data('style') || 'default';
                
                if (!examDate) return;
                
                try {
                    let targetDate;
                    if (examDate.includes('T')) {
                        targetDate = new Date(examDate).getTime();
                    } else {
                        targetDate = new Date(examDate + ' 00:00:00').getTime();
                    }
                    
                    if (!isNaN(targetDate)) {
                        this.updateSingleCountdown($countdown, targetDate, showTime, style);
                    }
                } catch (error) {
                    console.error('ECM Frontend: Error updating countdown:', error);
                }
            });
        },
        
        /**
         * レスポンシブ対応の初期化
         */
        initResponsive: function() {
            const resizeHandler = () => {
                this.adjustCountdownSize();
                this.adjustExamListLayout();
                this.adjustInlineLayouts();
            };
            
            $(window).on('resize', this.debounce(resizeHandler, 250));
            resizeHandler(); // 初回実行
        },
        
        /**
         * カウントダウンサイズの調整
         */
        adjustCountdownSize: function() {
            const windowWidth = $(window).width();
            
            $('.ecm-countdown').each(function() {
                const $countdown = $(this);
                
                if (windowWidth < 768) {
                    $countdown.addClass('ecm-mobile');
                } else {
                    $countdown.removeClass('ecm-mobile');
                }
                
                // 詳細表示の場合、小さい画面では縦並びに
                if (windowWidth < 600 && $countdown.hasClass('ecm-countdown-detailed') && 
                    !$countdown.hasClass('ecm-countdown-header') && 
                    !$countdown.hasClass('ecm-countdown-footer')) {
                    $countdown.addClass('ecm-vertical-layout');
                } else {
                    $countdown.removeClass('ecm-vertical-layout');
                }
            });
        },
        
        /**
         * 一列表示レイアウトの調整
         */
        adjustInlineLayouts: function() {
            const windowWidth = $(window).width();
            
            $('.ecm-countdown-header, .ecm-countdown-footer').each(function() {
                const $countdown = $(this);
                
                if (windowWidth < 480) {
                    // 非常に小さい画面では縦積みに
                    $countdown.addClass('ecm-very-small-screen');
                } else {
                    $countdown.removeClass('ecm-very-small-screen');
                }
            });
        },
        
        /**
         * 試験一覧レイアウトの調整
         */
        /**
         * 試験一覧レイアウトの調整
         */
        adjustExamListLayout: function() {
            const windowWidth = $(window).width();
            
            $('.ecm-exam-list').each(function() {
                const $list = $(this);
                
                // 元のカラムクラスを保存
                if (!$list.data('original-columns')) {
                    const classes = $list.attr('class').split(' ');
                    const columnClass = classes.find(cls => cls.startsWith('ecm-columns-'));
                    if (columnClass) {
                        $list.data('original-columns', columnClass);
                    }
                }
                
                if (windowWidth < 768) {
                    $list.removeClass('ecm-columns-2 ecm-columns-3 ecm-columns-4')
                         .addClass('ecm-columns-1');
                } else {
                    // 元のクラスを復元
                    const originalClass = $list.data('original-columns');
                    if (originalClass) {
                        $list.removeClass('ecm-columns-1').addClass(originalClass);
                    }
                }
            });
        },
        
        /**
         * アクセシビリティの初期化
         */
        initAccessibility: function() {
            // ARIA属性の設定
            $('.ecm-countdown').each(function() {
                const $countdown = $(this);
                const examName = $countdown.find('.ecm-exam-name').text() || '資格試験';
                
                $countdown.attr({
                    'role': 'timer',
                    'aria-label': `${examName}までのカウントダウン`,
                    'aria-live': 'polite'
                });
            });
            
            // キーボードナビゲーション
            $('.ecm-exam-item').attr('tabindex', '0').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
            
            // スクリーンリーダー用のライブ領域をセットアップ
            this.setupLiveRegion();
        },
        
        /**
         * ライブ領域のセットアップ
         */
        setupLiveRegion: function() {
            if ($('#ecm-live-region').length === 0) {
                $('body').append('<div id="ecm-live-region" aria-live="polite" aria-atomic="true" class="screen-reader-text"></div>');
            }
            
            // 緊急度が変わった時のアナウンス
            $(document).on('countdown:very-urgent', '.ecm-countdown', function(e, data) {
                const examName = $(this).find('.ecm-exam-name').text() || '試験';
                const message = `${examName}まであと${data.days}日です。準備を急いでください。`;
                $('#ecm-live-region').text(message);
            });
        },
        
        /**
         * アニメーションの初期化
         */
        initAnimations: function() {
            // CSS3アニメーションをサポートしているかチェック
            const supportsAnimation = typeof document.body.style.animationName !== 'undefined';
            
            if (!supportsAnimation) {
                $('.ecm-countdown').addClass('no-animation');
                return;
            }
            
            // Intersection Observer を使用した表示アニメーション
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            $(entry.target).addClass('ecm-visible');
                        }
                    });
                }, {
                    threshold: 0.1,
                    rootMargin: '50px'
                });
                
                $('.ecm-countdown, .ecm-exam-item').each(function() {
                    observer.observe(this);
                });
            } else {
                // フォールバック: すべて表示状態に
                $('.ecm-countdown, .ecm-exam-item').addClass('ecm-visible');
            }
            
            // 数値変更アニメーションの初期化
            this.initNumberChangeAnimation();
        },
        
        /**
         * 数値変更アニメーションの初期化
         */
        initNumberChangeAnimation: function() {
            $('.ecm-days-number, .ecm-number').each(function() {
                const $number = $(this);
                let lastValue = parseInt($number.text()) || 0;
                
                // MutationObserverがサポートされている場合のみ使用
                if ('MutationObserver' in window) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'childList' || mutation.type === 'characterData') {
                                const newValue = parseInt($number.text()) || 0;
                                if (newValue !== lastValue) {
                                    $number.addClass('ecm-number-changed');
                                    setTimeout(() => {
                                        $number.removeClass('ecm-number-changed');
                                    }, 300);
                                    lastValue = newValue;
                                }
                            }
                        });
                    });
                    
                    observer.observe(this, {
                        childList: true,
                        characterData: true,
                        subtree: true
                    });
                }
            });
        },
        
        /**
         * デバウンス関数
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * クリーンアップ
         */
        destroy: function() {
            this.timers.forEach(timerId => {
                clearInterval(timerId);
            });
            this.timers = [];
            this.initialized = false;
            console.log('ECM Frontend: Destroyed');
        },
        
        /**
         * 手動更新トリガー
         */
        forceUpdate: function() {
            console.log('ECM Frontend: Force update triggered');
            this.updateAllCountdowns();
        }
    };
    
    // 試験一覧インタラクション
    const ExamList = {
        /**
         * 初期化
         */
        init: function() {
            this.initSorting();
            this.initFiltering();
            this.initSearch();
            this.initInteractions();
        },
        
        /**
         * インタラクションの初期化
         */
        initInteractions: function() {
            // 試験アイテムのクリックイベント
            $(document).on('click', '.ecm-exam-item', function(e) {
                // リンクがクリックされた場合は無視
                if ($(e.target).is('a') || $(e.target).closest('a').length) {
                    return;
                }
                
                const examKey = $(this).data('exam-key');
                if (examKey) {
                    $(this).trigger('exam:selected', { examKey: examKey });
                }
            });
            
            // ホバー効果
            $(document).on('mouseenter', '.ecm-exam-item', function() {
                $(this).addClass('ecm-hover');
            }).on('mouseleave', '.ecm-exam-item', function() {
                $(this).removeClass('ecm-hover');
            });
        },
        
        /**
         * ソート機能の初期化（将来実装）
         */
        initSorting: function() {
            // 将来的な拡張: ソート機能
        },
        
        /**
         * フィルタリング機能の初期化（将来実装）
         */
        initFiltering: function() {
            // 将来的な拡張: フィルタリング機能
        },
        
        /**
         * 検索機能の初期化（将来実装）
         */
        initSearch: function() {
            // 将来的な拡張: 検索機能
        }
    };
    
    // ユーティリティ関数
    const Utils = {
        /**
         * 日付フォーマット
         */
        formatDate: function(date, format) {
            if (!date) return '';
            
            const d = new Date(date);
            if (isNaN(d.getTime())) return '';
            
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            
            switch (format) {
                case 'YYYY-MM-DD':
                    return `${year}-${month}-${day}`;
                case 'YYYY/MM/DD':
                    return `${year}/${month}/${day}`;
                case 'MM/DD':
                    return `${month}/${day}`;
                default:
                    return d.toLocaleDateString('ja-JP');
            }
        },
        
        /**
         * 数値を3桁区切りでフォーマット
         */
        formatNumber: function(num) {
            if (typeof num !== 'number') return num;
            return new Intl.NumberFormat('ja-JP').format(num);
        },
        
        /**
         * 相対時間の表示
         */
        getRelativeTime: function(date) {
            if (!date) return '';
            
            const now = new Date();
            const targetDate = new Date(date);
            const diffTime = targetDate - now;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays < 0) {
                return '終了済み';
            } else if (diffDays === 0) {
                return '今日';
            } else if (diffDays === 1) {
                return '明日';
            } else if (diffDays <= 7) {
                return `${diffDays}日後`;
            } else if (diffDays <= 30) {
                const weeks = Math.floor(diffDays / 7);
                return `約${weeks}週間後`;
            } else if (diffDays <= 365) {
                const months = Math.floor(diffDays / 30);
                return `約${months}ヶ月後`;
            } else {
                const years = Math.floor(diffDays / 365);
                return `約${years}年後`;
            }
        },
        
        /**
         * 要素が表示されているかチェック
         */
        isElementVisible: function(element) {
            const $element = $(element);
            if ($element.length === 0) return false;
            
            const rect = element.getBoundingClientRect();
            return rect.top >= 0 && rect.left >= 0 && 
                   rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && 
                   rect.right <= (window.innerWidth || document.documentElement.clientWidth);
        }
    };
    
    // 通知システム
    const Notifications = {
        permissionGranted: false,
        
        /**
         * 初期化
         */
        init: function() {
            this.checkPermission();
            this.setupNotificationEvents();
        },
        
        /**
         * 通知権限をチェック
         */
        checkPermission: function() {
            if (!('Notification' in window)) {
                console.log('ECM Notifications: Not supported in this browser');
                return false;
            }
            
            this.permissionGranted = Notification.permission === 'granted';
            
            if (Notification.permission === 'default') {
                this.requestPermission();
            }
            
            return this.permissionGranted;
        },
        
        /**
         * 通知権限をリクエスト
         */
        requestPermission: function() {
            if (!('Notification' in window)) return Promise.resolve(false);
            
            return Notification.requestPermission().then(permission => {
                this.permissionGranted = permission === 'granted';
                return this.permissionGranted;
            });
        },
        
        /**
         * 通知を表示
         */
        show: function(title, options = {}) {
            if (!this.permissionGranted) {
                return null;
            }
            
            const defaultOptions = {
                icon: window.ecmFrontend && window.ecmFrontend.plugin_url ? 
                     window.ecmFrontend.plugin_url + 'assets/images/icon.png' : '',
                requireInteraction: false,
                silent: false
            };
            
            const finalOptions = Object.assign({}, defaultOptions, options);
            
            try {
                const notification = new Notification(title, finalOptions);
                
                // 自動で閉じる
                setTimeout(() => {
                    notification.close();
                }, options.duration || 5000);
                
                return notification;
            } catch (e) {
                console.error('ECM Notifications: Failed to show notification', e);
                return null;
            }
        },
        
        /**
         * 通知イベントのセットアップ
         */
        setupNotificationEvents: function() {
            // 試験が近づいた時の通知
            $(document).on('countdown:urgent', '.ecm-countdown', (e, data) => {
                if (data.days <= 7 && data.days > 3) {
                    const examName = $(e.target).find('.ecm-exam-name').text() || '試験';
                    this.show(`${examName}まであと${data.days}日`, {
                        body: '試験日が近づいています。準備はお済みですか？',
                        tag: 'exam-urgent'
                    });
                }
            });
            
            // 非常に緊急な場合の通知
            $(document).on('countdown:very-urgent', '.ecm-countdown', (e, data) => {
                if (data.days <= 3) {
                    const examName = $(e.target).find('.ecm-exam-name').text() || '試験';
                    this.show(`${examName}まであと${data.days}日`, {
                        body: '試験日が迫っています！最終確認をお忘れなく。',
                        tag: 'exam-very-urgent',
                        requireInteraction: true
                    });
                }
            });
        }
    };
    
    // 設定管理
    const Settings = {
        defaults: {
            autoUpdate: true,
            soundNotifications: false,
            showSeconds: false,
            theme: 'default',
            animationsEnabled: true
        },
        
        /**
         * 設定を取得
         */
        get: function(key) {
            // LocalStorageは使用できないため、セッション内のみで管理
            if (!window.ecmSettings) {
                window.ecmSettings = this.defaults;
            }
            return key ? window.ecmSettings[key] : window.ecmSettings;
        },
        
        /**
         * 設定を保存
         */
        set: function(key, value) {
            if (!window.ecmSettings) {
                window.ecmSettings = this.defaults;
            }
            window.ecmSettings[key] = value;
            
            // 設定変更イベントを発火
            $(document).trigger('ecm:settings:changed', { key, value });
        }
    };
    
    // パフォーマンス監視
    const Performance = {
        startTime: Date.now(),
        
        /**
         * 初期化
         */
        init: function() {
            this.measureLoadTime();
        },
        
        /**
         * 読み込み時間を測定
         */
        measureLoadTime: function() {
            if ('performance' in window && 'now' in window.performance) {
                $(window).on('load', () => {
                    const loadTime = performance.now();
                    console.log(`ECM Frontend loaded in ${loadTime.toFixed(2)}ms`);
                    
                    // 初期化完了時間も記録
                    const initTime = Date.now() - this.startTime;
                    console.log(`ECM Frontend initialization took ${initTime}ms`);
                });
            }
        }
    };
    
    // エラーハンドリング
    const ErrorHandler = {
        /**
         * 初期化
         */
        init: function() {
            this.setupGlobalErrorHandler();
        },
        
        /**
         * グローバルエラーハンドラーのセットアップ
         */
        setupGlobalErrorHandler: function() {
            // JavaScript エラーをキャッチ
            window.addEventListener('error', (event) => {
                if (event.filename && event.filename.includes('exam-countdown-manager')) {
                    console.error('ECM Error:', {
                        message: event.message,
                        filename: event.filename,
                        lineno: event.lineno,
                        colno: event.colno
                    });
                }
            });
            
            // Promise rejection をキャッチ
            window.addEventListener('unhandledrejection', (event) => {
                console.error('ECM Unhandled Promise Rejection:', event.reason);
            });
        }
    };
    
    // メイン初期化処理
    function initializeECM() {
        console.log('ECM Frontend: Starting initialization...');
        
        try {
            // エラーハンドリングを最初に初期化
            ErrorHandler.init();
            
            // 基本機能の初期化
            ExamCountdown.init();
            ExamList.init();
            
            // 拡張機能の初期化
            Notifications.init();
            Performance.init();
            
            // 設定変更のイベントリスナー
            $(document).on('ecm:settings:changed', function(e, data) {
                console.log('ECM Setting changed:', data.key, '=', data.value);
                
                // 設定に応じた処理の再実行
                if (data.key === 'autoUpdate' && data.value) {
                    ExamCountdown.forceUpdate();
                }
                
                if (data.key === 'animationsEnabled') {
                    $('body').toggleClass('ecm-no-animations', !data.value);
                }
            });
            
            // カスタムイベントの設定
            $(document).on('exam:expired', '.ecm-countdown', function() {
                const examName = $(this).find('.ecm-exam-name').text();
                $(document).trigger('exam:finished', { examName });
            });
            
            // グローバルに公開
            window.ECM = {
                ExamCountdown,
                ExamList,
                Utils,
                Notifications,
                Settings,
                Performance,
                version: '1.0.2'
            };
            
            console.log('ECM Frontend: Initialization completed successfully');
            
        } catch (error) {
            console.error('ECM Frontend: Initialization failed', error);
        }
    }
    
    // DOM準備完了時の初期化
    $(document).ready(function() {
        initializeECM();
    });
    
    // ページ完全読み込み後の追加処理
    $(window).on('load', function() {
        // 遅延初期化が必要な場合の処理
        setTimeout(function() {
            if (window.ECM && window.ECM.ExamCountdown) {
                window.ECM.ExamCountdown.forceUpdate();
            }
        }, 100);
    });
    
    // ページ離脱時のクリーンアップ
    $(window).on('beforeunload', function() {
        if (window.ECM && window.ECM.ExamCountdown) {
            window.ECM.ExamCountdown.destroy();
        }
    });
    
    // AJAX完了後の再初期化（テーマやプラグインがAJAXでコンテンツを読み込む場合）
    $(document).ajaxComplete(function(event, xhr, settings) {
        // カウントダウン要素が新しく追加された場合の処理
        setTimeout(function() {
            const newCountdowns = $('.ecm-countdown[data-exam-date]:not([data-ecm-initialized])');
            if (newCountdowns.length > 0) {
                console.log('ECM Frontend: Found new countdown elements after AJAX, reinitializing...');
                newCountdowns.attr('data-ecm-initialized', 'true');
                if (window.ECM && window.ECM.ExamCountdown) {
                    window.ECM.ExamCountdown.initCountdowns();
                }
            }
        }, 100);
    });

})(jQuery);