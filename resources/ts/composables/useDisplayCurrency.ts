import { computed, ref } from "vue";
import CurrencyApi, { type Currency } from "@/api/currency";
import {
  displayCurrency,
  setDisplayCurrency,
  type DisplayCurrency,
} from "@/utils/displayCurrency";

const currencies = ref<Currency[]>([]);
const loading = ref(false);
let request: Promise<Currency[]> | null = null;

const toDisplayCurrency = (currency: Currency): DisplayCurrency => ({
  code: currency.code,
  symbol: currency.symbol,
  decimalPlaces: currency.decimalPlaces,
  rate: currency.rate,
  isBase: currency.isBase,
  isActive: currency.isActive,
});

const loadCurrencies = async () => {
  if (request) return request;

  loading.value = true;
  request = CurrencyApi.getAll()
    .then((response) => {
      currencies.value = response.data.filter((currency) => currency.isActive);
      const selected = currencies.value.find(
        (currency) => currency.code === displayCurrency.value?.code,
      );

      // Default to the current company base for an unchanged first visit.
      setDisplayCurrency(
        selected ??
          currencies.value.find((currency) => currency.isBase) ??
          null,
      );

      return currencies.value;
    })
    .finally(() => {
      loading.value = false;
      request = null;
    });

  return request;
};

export const useDisplayCurrency = () => {
  const selectedCode = computed<string | null>({
    get: () => displayCurrency.value?.code ?? null,
    set: (code) => {
      const currency = currencies.value.find((item) => item.code === code);
      if (currency) setDisplayCurrency(toDisplayCurrency(currency));
    },
  });

  const selectedCurrency = computed(
    () =>
      currencies.value.find(
        (currency) => currency.code === displayCurrency.value?.code,
      ) ?? displayCurrency.value,
  );

  const options = computed(() =>
    currencies.value.map((currency) => ({
      title: [currency.symbol, currency.code, currency.name]
        .filter(Boolean)
        .join(" · "),
      value: currency.code,
    })),
  );

  return {
    currencies,
    loading,
    options,
    selectedCode,
    selectedCurrency,
    loadCurrencies,
  };
};
