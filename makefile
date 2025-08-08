# DropLocker C++ Compression Makefile

CXX = g++
CXXFLAGS = -std=c++17 -O2 -Wall -Wextra
INCLUDES = -I./include
SRCDIR = src
BUILDDIR = build
SOURCES = $(SRCDIR)/compression.cpp $(SRCDIR)/main.cpp
TARGET = $(BUILDDIR)/droplocker_compressor

# Default target
all: $(TARGET)

# Create build directory
$(BUILDDIR):
	mkdir -p $(BUILDDIR)

# Compile the compressor
$(TARGET): $(SOURCES) | $(BUILDDIR)
	$(CXX) $(CXXFLAGS) $(INCLUDES) $(SOURCES) -o $(TARGET)

# Clean build files
clean:
	rm -rf $(BUILDDIR)

# Install (copy to system path - optional)
install: $(TARGET)
	cp $(TARGET) /usr/local/bin/

# Test the compressor
test: $(TARGET)
	@echo "Testing compressor..."
	@echo "Hello, DropLocker!" > test_file.txt
	@$(TARGET) compress test_file.txt test_compressed
	@$(TARGET) decompress test_compressed.huf test_decompressed.txt
	@echo "Test completed. Check test_decompressed.txt"

# Debug build
debug: CXXFLAGS += -g -DDEBUG
debug: $(TARGET)

.PHONY: all clean install test debug
