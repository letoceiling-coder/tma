import { useEffect, useState, useCallback } from "react";

interface TelegramUser {
  id: number;
  first_name: string;
  last_name?: string;
  username?: string;
  language_code?: string;
  photo_url?: string;
}

interface TelegramWebApp {
  ready: () => void;
  expand: () => void;
  close: () => void;
  enableClosingConfirmation: () => void;
  disableClosingConfirmation: () => void;
  setHeaderColor: (color: string) => void;
  setBackgroundColor: (color: string) => void;
  initData: string;
  initDataUnsafe: {
    user?: TelegramUser;
    query_id?: string;
    start_param?: string;
  };
  MainButton: {
    text: string;
    show: () => void;
    hide: () => void;
    onClick: (callback: () => void) => void;
    offClick: (callback: () => void) => void;
    setParams: (params: { text?: string; color?: string; text_color?: string; is_active?: boolean }) => void;
  };
  BackButton: {
    show: () => void;
    hide: () => void;
    onClick: (callback: () => void) => void;
    offClick: (callback: () => void) => void;
  };
  HapticFeedback: {
    impactOccurred: (style: "light" | "medium" | "heavy" | "rigid" | "soft") => void;
    notificationOccurred: (type: "error" | "success" | "warning") => void;
    selectionChanged: () => void;
  };
  openTelegramLink: (url: string) => void;
  openLink: (url: string) => void;
  shareLink?: (url: string, text: string) => Promise<void>;
  platform: string;
  version: string;
  colorScheme: "light" | "dark";
  themeParams: {
    bg_color?: string;
    text_color?: string;
    hint_color?: string;
    link_color?: string;
    button_color?: string;
    button_text_color?: string;
  };
}

declare global {
  interface Window {
    Telegram?: {
      WebApp: TelegramWebApp;
    };
  }
}

export const useTelegramWebApp = () => {
  const [isReady, setIsReady] = useState(false);
  const [user, setUser] = useState<TelegramUser | null>(null);
  const [isTelegram, setIsTelegram] = useState(false);

  useEffect(() => {
    const tg = window.Telegram?.WebApp;
    
    if (tg) {
      setIsTelegram(true);
      
      // Initialize WebApp
      tg.ready();
      tg.expand();
      
      // Set colors to match app theme
      tg.setHeaderColor('#F8A575');
      tg.setBackgroundColor('#F8A575');
      
      // Get user data
      const userData = tg.initDataUnsafe?.user;
      if (userData) {
        setUser(userData);
      }
      
      setIsReady(true);
    } else {
      // Not in Telegram - use mock data for development
      setIsTelegram(false);
      
      // Mock user for testing outside Telegram
      const mockUser: TelegramUser = {
        id: 999999999,
        first_name: 'Dev',
        last_name: 'User',
        username: 'devuser',
        language_code: 'ru',
      };
      
      setUser(mockUser);
      setIsReady(true);
    }
  }, []);

  const close = useCallback(() => {
    window.Telegram?.WebApp?.close();
  }, []);

  const expand = useCallback(() => {
    window.Telegram?.WebApp?.expand();
  }, []);

  const share = useCallback(async (url: string, text: string): Promise<boolean> => {
    const tg = window.Telegram?.WebApp;
    
    // Try Telegram.WebApp.shareLink() first (preferred method for Mini Apps)
    if (tg?.shareLink) {
      try {
        await tg.shareLink(url, text);
        return true;
      } catch (error) {
        console.error('Telegram shareLink failed:', error);
        // Fall through to next method
      }
    }
    
    // Fallback to openTelegramLink with share URL
    if (tg?.openTelegramLink) {
      try {
        tg.openTelegramLink(
          `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`
        );
        return true;
      } catch (error) {
        console.error('Telegram openTelegramLink failed:', error);
      }
    }
    
    return false;
  }, []);

  const showMainButton = useCallback((text: string, onClick: () => void) => {
    const tg = window.Telegram?.WebApp;
    if (tg?.MainButton) {
      tg.MainButton.setParams({
        text,
        color: '#E07C63',
        text_color: '#FFFFFF',
        is_active: true
      });
      tg.MainButton.onClick(onClick);
      tg.MainButton.show();
    }
  }, []);

  const hideMainButton = useCallback(() => {
    window.Telegram?.WebApp?.MainButton?.hide();
  }, []);

  const haptic = useCallback((type: 'light' | 'medium' | 'heavy' | 'success' | 'error' | 'warning' | 'selection') => {
    const tg = window.Telegram?.WebApp;
    if (tg?.HapticFeedback) {
      if (type === 'success' || type === 'error' || type === 'warning') {
        tg.HapticFeedback.notificationOccurred(type);
      } else if (type === 'selection') {
        tg.HapticFeedback.selectionChanged();
      } else {
        tg.HapticFeedback.impactOccurred(type);
      }
    }
  }, []);

  // Get initData - real or mock
  const getInitData = useCallback(() => {
    const tg = window.Telegram?.WebApp;
    if (tg?.initData) {
      return tg.initData;
    }
    
    // Mock initData for development (outside Telegram)
    // В продакшене backend проверяет APP_DEBUG и пропускает валидацию
    return 'mock_init_data_for_development';
  }, []);

  return {
    isReady,
    isTelegram,
    user,
    userName: user?.first_name || user?.username || "Гость",
    userPhoto: user?.photo_url,
    initData: getInitData(),
    close,
    expand,
    share,
    showMainButton,
    hideMainButton,
    haptic,
    platform: window.Telegram?.WebApp?.platform || 'web',
    colorScheme: window.Telegram?.WebApp?.colorScheme || 'light',
  };
};

export default useTelegramWebApp;
