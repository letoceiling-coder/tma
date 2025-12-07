import { useState, useEffect } from "react";
import LoadingScreen from "@/components/LoadingScreen";
import MainWheel from "./MainWheel";
import StartMenu from "@/components/StartMenu";

const LOADING_KEY = "app_loading_complete";
const START_MENU_KEY = "start_menu_shown";

const Index = () => {
  const [isLoading, setIsLoading] = useState(() => {
    // Check if app was already loaded in this session
    return !sessionStorage.getItem(LOADING_KEY);
  });
  
  const [showStartMenu, setShowStartMenu] = useState(() => {
    // Показываем стартовое меню только при первом запуске в сессии
    return !sessionStorage.getItem(START_MENU_KEY);
  });
  
  const [channelUsernames, setChannelUsernames] = useState<string[]>([]);

  useEffect(() => {
    if (!isLoading) {
      // Mark loading as complete for this session
      sessionStorage.setItem(LOADING_KEY, "true");
    }
  }, [isLoading]);

  // Загружаем список каналов
  useEffect(() => {
    const loadChannels = async () => {
      try {
        const apiUrl = import.meta.env.VITE_API_URL || '';
        const apiPath = apiUrl ? `${apiUrl}/api/channels` : `/api/channels`;
        
        const response = await fetch(apiPath, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        });

        if (response.ok) {
          const data = await response.json();
          const channels = data.channels || [];
          setChannelUsernames(channels.map((ch: any) => ch.username));
        }
      } catch (error) {
        console.error('Ошибка загрузки каналов:', error);
      }
    };

    loadChannels();
  }, []);

  const handleStartGame = () => {
    sessionStorage.setItem(START_MENU_KEY, "true");
    setShowStartMenu(false);
  };

  if (isLoading) {
    return <LoadingScreen onComplete={() => setIsLoading(false)} />;
  }

  if (showStartMenu) {
    return <StartMenu channelUsernames={channelUsernames} onStartGame={handleStartGame} />;
  }

  return <MainWheel />;
};

export default Index;
