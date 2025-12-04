import { useState, useCallback } from "react";

interface UserInitResult {
  success: boolean;
  user?: {
    id: number;
    telegram_id: number;
    name: string;
    username: string | null;
    avatar_url: string | null;
    tickets_available: number;
    stars_balance: number;
    total_spins: number;
    total_wins: number;
  };
  is_new_user?: boolean;
  error?: string;
}

export const useUserInit = () => {
  const [isInitializing, setIsInitializing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const initUser = useCallback(async (): Promise<UserInitResult | null> => {
    const tg = window.Telegram?.WebApp;

    if (!tg?.initData) {
      console.warn("Telegram initData not available - cannot initialize user");
      return null;
    }

    setIsInitializing(true);
    setError(null);

    try {
      const apiUrl = import.meta.env.VITE_API_URL || "";
      const apiPath = apiUrl ? `${apiUrl}/api/user/init` : `/api/user/init`;

      const response = await fetch(apiPath, {
        method: "POST",
        headers: {
          "X-Telegram-Init-Data": tg.initData || "",
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || data.error || "Ошибка инициализации пользователя");
      }

      console.log("User initialized:", data);

      return {
        success: true,
        user: data.user,
        is_new_user: data.is_new_user,
      };
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : "Неизвестная ошибка";
      console.error("Error initializing user:", errorMessage);
      setError(errorMessage);

      return {
        success: false,
        error: errorMessage,
      };
    } finally {
      setIsInitializing(false);
    }
  }, []);

  return {
    initUser,
    isInitializing,
    error,
  };
};

