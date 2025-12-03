import { useState, useEffect } from "react";
import LoadingScreen from "@/components/LoadingScreen";
import MainWheel from "./MainWheel";

const LOADING_KEY = "app_loading_complete";

const Index = () => {
  const [isLoading, setIsLoading] = useState(() => {
    // Check if app was already loaded in this session
    return !sessionStorage.getItem(LOADING_KEY);
  });

  useEffect(() => {
    if (!isLoading) {
      // Mark loading as complete for this session
      sessionStorage.setItem(LOADING_KEY, "true");
    }
  }, [isLoading]);

  if (isLoading) {
    return <LoadingScreen onComplete={() => setIsLoading(false)} />;
  }

  return <MainWheel />;
};

export default Index;
